<?php

$pdo=Database::getConnection();

$userId=(int)($_SESSION['user_id']??$_SESSION['id']??0);
$businessId=(int)($_SESSION['business_id']??0);

if($userId<=0){
    header('Location: index.php?page=login');
    exit;
}

if($businessId<=0){
    header('Location: index.php?page=select_business');
    exit;
}

$error='';
$success='';

/* ============================================================
   HELPERS
   ============================================================ */

function calculateNumberOfPayments($termValue,$termUnit,$frequency){
    $termValue=max(1,(int)$termValue);

    if($termUnit==='months'){
        return match($frequency){
            'daily'=>$termValue*30,
            'weekly'=>$termValue*4,
            'biweekly'=>$termValue*2,
            default=>$termValue
        };
    }

    return match($frequency){
        'daily'=>$termValue,
        'weekly'=>max(1,(int)ceil($termValue/7)),
        'biweekly'=>max(1,(int)ceil($termValue/14)),
        default=>max(1,(int)ceil($termValue/30))
    };
}

function getNextScheduleDate(DateTime $date,$frequency){
    $newDate=clone $date;

    return match($frequency){
        'daily'=>$newDate->modify('+1 day'),
        'weekly'=>$newDate->modify('+1 week'),
        'biweekly'=>$newDate->modify('+2 weeks'),
        default=>$newDate->modify('+1 month')
    };
}

/* ============================================================
   RECORD PAYMENT
   ============================================================ */

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['record_payment'])){

    $paymentLoanId=(int)($_POST['loan_id']??0);
    $selectedScheduleIds=$_POST['schedule_ids']??[];

    if(!is_array($selectedScheduleIds)){
        $selectedScheduleIds=[];
    }

    $selectedScheduleIds=array_values(array_filter(
        array_map('intval',$selectedScheduleIds),
        fn($id)=>$id>0
    ));

    $customPaymentAmount=round(
        (float)($_POST['custom_payment_amount']??0),
        2
    );

    $paymentDate=$_POST['payment_date']??date('Y-m-d');
    $paymentNotes=trim($_POST['payment_notes']??'');

    if($paymentLoanId<=0){
        $error='Invalid loan selection.';
    }else{

        try{

            $pdo->beginTransaction();

            /* LOCK LOAN */

            $loanStmt=$pdo->prepare("
                SELECT
                    l.*,
                    a.account_name,
                    a.balance AS account_balance
                FROM loans l
                INNER JOIN loan_accounts a
                    ON a.id=l.account_id
                    AND a.business_id=l.business_id
                WHERE l.id=?
                AND l.business_id=?
                AND l.created_by=?
                FOR UPDATE
            ");

            $loanStmt->execute([
                $paymentLoanId,
                $businessId,
                $userId
            ]);

            $loan=$loanStmt->fetch(PDO::FETCH_ASSOC);

            if(!$loan){
                throw new Exception(
                    'Loan not found or you do not have permission to access this loan.'
                );
            }

            $loanAccountId=(int)$loan['account_id'];
            $paymentType=$loan['payment_type']??'installment';

            if($loanAccountId<=0){
                throw new Exception(
                    'This loan does not have a valid funding account.'
                );
            }

            /* LOCK ACCOUNT */

            $accountCheck=$pdo->prepare("
                SELECT id
                FROM loan_accounts
                WHERE id=?
                AND business_id=?
                FOR UPDATE
            ");

            $accountCheck->execute([
                $loanAccountId,
                $businessId
            ]);

            if(!$accountCheck->fetchColumn()){
                throw new Exception(
                    'The funding account linked to this loan no longer exists.'
                );
            }

            /* TOTAL PAYMENTS */

            $sumStmt=$pdo->prepare("
                SELECT COALESCE(SUM(payment_amount),0)
                FROM loan_payments
                WHERE loan_id=?
                AND business_id=?
            ");

            $sumStmt->execute([
                $paymentLoanId,
                $businessId
            ]);

            $totalPaidBefore=round(
                (float)$sumStmt->fetchColumn(),
                2
            );

            $totalPayable=round(
                (float)$loan['total_payable'],
                2
            );

            $remainingBefore=round(
                $totalPayable-$totalPaidBefore,
                2
            );

            if($remainingBefore<=0){
                throw new Exception(
                    'This loan is already fully paid.'
                );
            }

            $paymentAmount=0;

            /* =================================================
               LUMP SUM
               ================================================= */

            if($paymentType==='lump_sum'){

                /*
                 * Lump sum ALWAYS allows a custom amount.
                 *
                 * Example:
                 * Total = 5,500
                 * Payment = 500
                 * Remaining = 5,000
                 */

                $paymentAmount=$customPaymentAmount;

                if($paymentAmount<=0){
                    throw new Exception(
                        'Please enter the payment amount.'
                    );
                }

            }

            /* =================================================
               INSTALLMENT
               ================================================= */

            else{

                if(!empty($selectedScheduleIds)){

                    $placeholders=implode(
                        ',',
                        array_fill(
                            0,
                            count($selectedScheduleIds),
                            '?'
                        )
                    );

                    $scheduleStmt=$pdo->prepare("
                        SELECT
                            id,
                            due_date,
                            amount_due,
                            status
                        FROM loan_schedules
                        WHERE loan_id=?
                        AND business_id=?
                        AND id IN($placeholders)
                        ORDER BY due_date ASC,id ASC
                        FOR UPDATE
                    ");

                    $scheduleStmt->execute(
                        array_merge(
                            [
                                $paymentLoanId,
                                $businessId
                            ],
                            $selectedScheduleIds
                        )
                    );

                    $selectedSchedules=
                        $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

                    if(
                        count($selectedSchedules)!==
                        count($selectedScheduleIds)
                    ){
                        throw new Exception(
                            'One or more selected payment schedules could not be found.'
                        );
                    }

                    foreach($selectedSchedules as $schedule){

                        if(($schedule['status']??'')==='paid'){
                            continue;
                        }

                        $paymentAmount+=round(
                            (float)$schedule['amount_due'],
                            2
                        );
                    }

                    $paymentAmount=round(
                        $paymentAmount,
                        2
                    );

                }else{

                    /*
                     * Installment loans can also make
                     * a custom partial payment.
                     */

                    $paymentAmount=$customPaymentAmount;
                }

                if($paymentAmount<=0){
                    throw new Exception(
                        'Please select an installment or enter a payment amount.'
                    );
                }
            }

            /* =================================================
               VALIDATE PAYMENT
               ================================================= */

            if($paymentAmount>$remainingBefore){
                throw new Exception(
                    'Payment amount exceeds the remaining loan balance of ₱'.
                    number_format($remainingBefore,2)
                );
            }

            /* =================================================
               INSERT PAYMENT
               ================================================= */

            $payStmt=$pdo->prepare("
                INSERT INTO loan_payments(
                    business_id,
                    loan_id,
                    account_id,
                    payment_amount,
                    payment_date,
                    notes
                )
                VALUES(?,?,?,?,?,?)
            ");

            $payStmt->execute([
                $businessId,
                $paymentLoanId,
                $loanAccountId,
                $paymentAmount,
                $paymentDate,
                $paymentNotes
            ]);

            $totalPaidAfter=round(
                $totalPaidBefore+$paymentAmount,
                2
            );

            /* =================================================
               UPDATE SCHEDULE STATUS
               ================================================= */

            $allScheduleStmt=$pdo->prepare("
                SELECT
                    id,
                    amount_due,
                    status
                FROM loan_schedules
                WHERE loan_id=?
                AND business_id=?
                ORDER BY due_date ASC,id ASC
                FOR UPDATE
            ");

            $allScheduleStmt->execute([
                $paymentLoanId,
                $businessId
            ]);

            $allSchedules=
                $allScheduleStmt->fetchAll(PDO::FETCH_ASSOC);

            if(!empty($allSchedules)){

                $remainingPaymentForSchedules=$totalPaidAfter;

                $updateScheduleStatus=$pdo->prepare("
                    UPDATE loan_schedules
                    SET status=?
                    WHERE id=?
                    AND loan_id=?
                    AND business_id=?
                ");

                foreach($allSchedules as $schedule){

                    $scheduleAmount=round(
                        (float)$schedule['amount_due'],
                        2
                    );

                    if($remainingPaymentForSchedules>=$scheduleAmount){

                        $scheduleStatus='paid';

                    }elseif($remainingPaymentForSchedules>0){

                        $scheduleStatus='partially_paid';

                    }else{

                        $scheduleStatus='unpaid';
                    }

                    $updateScheduleStatus->execute([
                        $scheduleStatus,
                        $schedule['id'],
                        $paymentLoanId,
                        $businessId
                    ]);

                    $remainingPaymentForSchedules=max(
                        0,
                        round(
                            $remainingPaymentForSchedules-
                            $scheduleAmount,
                            2
                        )
                    );
                }
            }

            /* =================================================
               LOAN STATUS
               ================================================= */

            $newStatus=
                $totalPaidAfter>=$totalPayable
                ?'completed'
                :'active';

            $updateLoanStmt=$pdo->prepare("
                UPDATE loans
                SET status=?
                WHERE id=?
                AND business_id=?
                AND created_by=?
            ");

            $updateLoanStmt->execute([
                $newStatus,
                $paymentLoanId,
                $businessId,
                $userId
            ]);

            /* =================================================
               RETURN MONEY TO ACCOUNT
               ================================================= */

            $updateAcc=$pdo->prepare("
                UPDATE loan_accounts
                SET balance=balance+?
                WHERE id=?
                AND business_id=?
            ");

            $updateAcc->execute([
                $paymentAmount,
                $loanAccountId,
                $businessId
            ]);

            /* =================================================
               ACCOUNT TRANSACTION
               ================================================= */

            $txStmt=$pdo->prepare("
                INSERT INTO loan_account_transactions(
                    business_id,
                    account_id,
                    type,
                    amount,
                    description
                )
                VALUES(?,?, 'CREDIT',?,?)
            ");

            $txStmt->execute([
                $businessId,
                $loanAccountId,
                $paymentAmount,
                "Payment received for Loan #{$paymentLoanId}"
            ]);

            $pdo->commit();

            header(
                'Location: index.php?page=loans&success_payment=1'
            );

            exit;

        }catch(Exception $e){

            if($pdo->inTransaction()){
                $pdo->rollBack();
            }

            $error=
                'Failed to record payment: '.
                $e->getMessage();
        }
    }
}

/* ============================================================
   ISSUE LOAN
   ============================================================ */

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['issue_loan'])){

    $borrowerId=(int)($_POST['borrower_id']??0);
    $accountId=(int)($_POST['account_id']??0);

    $referenceNumber=trim(
        $_POST['reference_number']??''
    );

    $referenceNumber=
        $referenceNumber!==''?
        $referenceNumber:
        null;

    $principalAmount=round(
        (float)($_POST['principal_amount']??0),
        2
    );

    $interestRate=round(
        (float)($_POST['interest_rate']??0),
        2
    );

    $termValue=(int)(
        $_POST['term_value']??30
    );

    $termUnit=$_POST['term_unit']??'days';

    $paymentFrequency=
        $_POST['payment_frequency']??'monthly';

    $paymentType=
        $_POST['payment_type']??'installment';

    $loanDate=
        $_POST['loan_date']??date('Y-m-d');

    /* VALIDATE PAYMENT TYPE */

    if(!in_array(
        $paymentType,
        ['installment','lump_sum'],
        true
    )){
        $paymentType='installment';
    }

    /* VALIDATE FREQUENCY */

    $allowedFrequencies=[
        'daily',
        'weekly',
        'biweekly',
        'monthly'
    ];

    if(!in_array(
        $paymentFrequency,
        $allowedFrequencies,
        true
    )){
        $paymentFrequency='monthly';
    }

    /* VALIDATE TERM */

    $allowedTermUnits=[
        'days',
        'months'
    ];

    if(!in_array(
        $termUnit,
        $allowedTermUnits,
        true
    )){
        $termUnit='days';
    }

    /* COLLATERAL */

    $collateralItem=trim(
        $_POST['collateral_item']??''
    );

    $collateralDesc=trim(
        $_POST['collateral_description']??''
    );

    $collateralValue=round(
        (float)($_POST['collateral_value']??0),
        2
    );

    if(
        $borrowerId<=0||
        $accountId<=0||
        $principalAmount<=0||
        $termValue<=0
    ){

        $error=
            'Please fill in all required fields correctly.';

    }else{

        /* CALCULATE DUE DATE */

        try{

            $dateObj=new DateTime($loanDate);

            if($termUnit==='months'){

                $dateObj->modify(
                    "+{$termValue} months"
                );

            }else{

                $dateObj->modify(
                    "+{$termValue} days"
                );
            }

            $dueDate=$dateObj->format('Y-m-d');

        }catch(Exception $e){

            $error='Invalid loan date.';
            $dueDate=null;
        }

        /* =====================================================
           CHECK BORROWER
           ===================================================== */

        if(!$error){

            $borrowerStmt=$pdo->prepare("
                SELECT id
                FROM loan_borrowers
                WHERE id=?
                AND business_id=?
                AND created_by=?
                LIMIT 1
            ");

            $borrowerStmt->execute([
                $borrowerId,
                $businessId,
                $userId
            ]);

            if(!$borrowerStmt->fetchColumn()){

                $error=
                    'Selected borrower was not found or does not belong to your account.';
            }
        }

        /* =====================================================
           CHECK ACCOUNT
           ===================================================== */

        if(!$error){

            $accStmt=$pdo->prepare("
                SELECT *
                FROM loan_accounts
                WHERE id=?
                AND business_id=?
                FOR UPDATE
            ");

            $accStmt->execute([
                $accountId,
                $businessId
            ]);

            $account=
                $accStmt->fetch(PDO::FETCH_ASSOC);

            if(!$account){

                $error=
                    'Selected funding account was not found.';

            }elseif(
                (float)$account['balance']<
                $principalAmount
            ){

                $error=
                    'Insufficient funds in the selected account/wallet.';
            }
        }

        /* =====================================================
           CREATE LOAN
           ===================================================== */

        if(!$error){

            try{

                $pdo->beginTransaction();

                /* RECHECK BORROWER */

                $borrowerCheck=$pdo->prepare("
                    SELECT id
                    FROM loan_borrowers
                    WHERE id=?
                    AND business_id=?
                    AND created_by=?
                    FOR UPDATE
                ");

                $borrowerCheck->execute([
                    $borrowerId,
                    $businessId,
                    $userId
                ]);

                if(!$borrowerCheck->fetchColumn()){

                    throw new Exception(
                        'Borrower does not belong to your account.'
                    );
                }

                /* RECHECK ACCOUNT */

                $accountCheck=$pdo->prepare("
                    SELECT id,balance
                    FROM loan_accounts
                    WHERE id=?
                    AND business_id=?
                    FOR UPDATE
                ");

                $accountCheck->execute([
                    $accountId,
                    $businessId
                ]);

                $account=
                    $accountCheck->fetch(PDO::FETCH_ASSOC);

                if(!$account){

                    throw new Exception(
                        'Funding account was not found.'
                    );
                }

                if(
                    (float)$account['balance']<
                    $principalAmount
                ){

                    throw new Exception(
                        'The account balance is no longer sufficient for this loan.'
                    );
                }

                /* =================================================
                   CALCULATE LOAN
                   ================================================= */

                $interestAmount=round(
                    $principalAmount*
                    ($interestRate/100),
                    2
                );

                $totalPayable=round(
                    $principalAmount+
                    $interestAmount,
                    2
                );

                /*
                 * Lump sum = exactly ONE payment.
                 */

                if($paymentType==='lump_sum'){

                    $numberOfPayments=1;

                    $fixedPaymentAmount=
                        $totalPayable;

                }else{

                    $numberOfPayments=max(
                        1,
                        calculateNumberOfPayments(
                            $termValue,
                            $termUnit,
                            $paymentFrequency
                        )
                    );

                    $fixedPaymentAmount=round(
                        $totalPayable/
                        $numberOfPayments,
                        2
                    );
                }

                /* =================================================
                   INSERT LOAN
                   ================================================= */

                $loanStmt=$pdo->prepare("
                    INSERT INTO loans(
                        business_id,
                        created_by,
                        borrower_id,
                        account_id,
                        reference_number,
                        principal_amount,
                        interest_rate,
                        total_payable,
                        loan_date,
                        due_date,
                        term_days,
                        term_unit,
                        payment_frequency,
                        payment_type,
                        fixed_payment_amount,
                        status
                    )
                    VALUES(
                        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active'
                    )
                ");

                $loanStmt->execute([
                    $businessId,
                    $userId,
                    $borrowerId,
                    $accountId,
                    $referenceNumber,
                    $principalAmount,
                    $interestRate,
                    $totalPayable,
                    $loanDate,
                    $dueDate,
                    $termValue,
                    $termUnit,
                    $paymentFrequency,
                    $paymentType,
                    $fixedPaymentAmount
                ]);

                $loanId=(int)$pdo->lastInsertId();

                /* =================================================
                   CREATE PAYMENT SCHEDULE
                   ================================================= */

                $scheduleInsert=$pdo->prepare("
                    INSERT INTO loan_schedules(
                        business_id,
                        loan_id,
                        due_date,
                        amount_due,
                        status
                    )
                    VALUES(?,?,?,?, 'unpaid')
                ");

                /*
                 * LUMP SUM
                 *
                 * One schedule only.
                 */

                if($paymentType==='lump_sum'){

                    $scheduleInsert->execute([
                        $businessId,
                        $loanId,
                        $dueDate,
                        $totalPayable
                    ]);

                }else{

                    $scheduleDate=
                        new DateTime($loanDate);

                    $scheduledTotal=0;

                    for(
                        $i=1;
                        $i<=$numberOfPayments;
                        $i++
                    ){

                        $scheduleDate=
                            getNextScheduleDate(
                                $scheduleDate,
                                $paymentFrequency
                            );

                        $scheduleAmount=
                            $i===$numberOfPayments
                            ?round(
                                $totalPayable-
                                $scheduledTotal,
                                2
                            )
                            :$fixedPaymentAmount;

                        $scheduledTotal+=
                            $scheduleAmount;

                        $scheduleInsert->execute([
                            $businessId,
                            $loanId,
                            $scheduleDate->format('Y-m-d'),
                            $scheduleAmount
                        ]);
                    }
                }

                /* =================================================
                   COLLATERAL IMAGE
                   ================================================= */

                $imagePath=null;

                if(
                    $collateralItem!==''&&
                    isset($_FILES['collateral_image'])
                ){

                    $uploadError=
                        $_FILES['collateral_image']['error'];

                    if(
                        $uploadError===
                        UPLOAD_ERR_OK
                    ){

                        $fileTmpPath=
                            $_FILES['collateral_image']['tmp_name'];

                        $fileName=
                            $_FILES['collateral_image']['name'];

                        $fileExtension=
                            strtolower(
                                pathinfo(
                                    $fileName,
                                    PATHINFO_EXTENSION
                                )
                            );

                        $allowedExtensions=[
                            'jpg',
                            'jpeg',
                            'png',
                            'webp'
                        ];

                        if(!in_array(
                            $fileExtension,
                            $allowedExtensions,
                            true
                        )){

                            throw new Exception(
                                'Invalid collateral image format. Allowed: JPG, JPEG, PNG, WEBP.'
                            );
                        }

                        $newFileName=
                            'col_'.
                            $loanId.'_'.
                            time().
                            '.'.
                            $fileExtension;

                        $uploadFileDir=
                            __DIR__.
                            '/../../../public/uploads/collaterals/';

                        if(!is_dir($uploadFileDir)){

                            if(
                                !mkdir(
                                    $uploadFileDir,
                                    0755,
                                    true
                                )&&
                                !is_dir($uploadFileDir)
                            ){

                                throw new Exception(
                                    'Failed to create collateral upload directory.'
                                );
                            }
                        }

                        $destPath=
                            $uploadFileDir.
                            $newFileName;

                        if(!move_uploaded_file(
                            $fileTmpPath,
                            $destPath
                        )){

                            throw new Exception(
                                'Failed to move uploaded collateral image.'
                            );
                        }

                        $imagePath=
                            'uploads/collaterals/'.
                            $newFileName;

                    }elseif(
                        $uploadError!==
                        UPLOAD_ERR_NO_FILE
                    ){

                        throw new Exception(
                            'Collateral image upload failed. Error code: '.
                            $uploadError
                        );
                    }
                }

                /* =================================================
                   COLLATERAL
                   ================================================= */

                if($collateralItem!==''){

                    $colStmt=$pdo->prepare("
                        INSERT INTO loan_collaterals(
                            business_id,
                            loan_id,
                            item_name,
                            description,
                            estimated_value,
                            image_path
                        )
                        VALUES(?,?,?,?,?,?)
                    ");

                    $colStmt->execute([
                        $businessId,
                        $loanId,
                        $collateralItem,
                        $collateralDesc,
                        $collateralValue,
                        $imagePath
                    ]);
                }

                /* =================================================
                   DEDUCT ACCOUNT BALANCE
                   ================================================= */

                $updateAcc=$pdo->prepare("
                    UPDATE loan_accounts
                    SET balance=balance-?
                    WHERE id=?
                    AND business_id=?
                    AND balance>=?
                ");

                $updateAcc->execute([
                    $principalAmount,
                    $accountId,
                    $businessId,
                    $principalAmount
                ]);

                if($updateAcc->rowCount()!==1){

                    throw new Exception(
                        'The account balance is no longer sufficient for this loan.'
                    );
                }

                /* =================================================
                   ACCOUNT TRANSACTION
                   ================================================= */

                $txStmt=$pdo->prepare("
                    INSERT INTO loan_account_transactions(
                        business_id,
                        account_id,
                        type,
                        amount,
                        description
                    )
                    VALUES(?,?, 'DEBIT',?,?)
                ");

                $txStmt->execute([
                    $businessId,
                    $accountId,
                    $principalAmount,
                    "Loan #{$loanId} disbursement"
                ]);

                $pdo->commit();

                header(
                    'Location: index.php?page=loans&success=1'
                );

                exit;

            }catch(Exception $e){

                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }

                $error=
                    'Failed to issue loan: '.
                    $e->getMessage();
            }
        }
    }
}

/* ============================================================
   SUCCESS
   ============================================================ */

if(isset($_GET['success'])){
    $success=
        'Loan issued successfully with payment schedule!';
}

if(isset($_GET['success_payment'])){
    $success=
        'Payment recorded successfully!';
}

/* ============================================================
   PAGE
   ============================================================ */

$activePage='loans';
$pageTitle='Loans Management - Loan Management';

$search=trim(
    $_GET['search']??''
);

$statusFilter=strtolower(
    trim($_GET['status']??'all')
);

$sort=$_GET['sort']??'newest';

/* ============================================================
   LOAD LOANS
   ============================================================ */

$stmt=$pdo->prepare("
    SELECT
        l.*,
        CONCAT(
            b.first_name,
            ' ',
            b.last_name
        ) AS borrower_name,
        a.account_name,
        c.item_name,
        c.description AS collateral_desc,
        c.estimated_value,
        c.image_path,
        COALESCE(
            SUM(p.payment_amount),
            0
        ) AS total_paid,
        (
            l.total_payable-
            COALESCE(
                SUM(p.payment_amount),
                0
            )
        ) AS remaining_balance
    FROM loans l
    INNER JOIN loan_borrowers b
        ON l.borrower_id=b.id
        AND b.business_id=l.business_id
        AND b.created_by=l.created_by
    INNER JOIN loan_accounts a
        ON l.account_id=a.id
        AND a.business_id=l.business_id
    LEFT JOIN loan_collaterals c
        ON l.id=c.loan_id
        AND c.business_id=l.business_id
    LEFT JOIN loan_payments p
        ON l.id=p.loan_id
        AND p.business_id=l.business_id
    WHERE l.business_id=?
    AND l.created_by=?
    GROUP BY l.id
    ORDER BY l.created_at DESC
");

$stmt->execute([
    $businessId,
    $userId
]);

$loans=$stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   LOAD SCHEDULES
   ============================================================ */

$loanSchedules=[];

if(!empty($loans)){

    $loanIds=array_values(
        array_unique(
            array_map(
                fn($loan)=>(int)$loan['id'],
                $loans
            )
        )
    );

    $placeholders=implode(
        ',',
        array_fill(
            0,
            count($loanIds),
            '?'
        )
    );

    $scheduleStmt=$pdo->prepare("
        SELECT
            id,
            loan_id,
            due_date,
            amount_due,
            status
        FROM loan_schedules
        WHERE loan_id IN($placeholders)
        AND business_id=?
        ORDER BY due_date ASC,id ASC
    ");

    $scheduleStmt->execute(
        array_merge(
            $loanIds,
            [$businessId]
        )
    );

    foreach(
        $scheduleStmt->fetchAll(PDO::FETCH_ASSOC)
        as $schedule
    ){

        $loanId=(int)$schedule['loan_id'];

        $loanSchedules[$loanId][]=
            $schedule;
    }
}

/* ============================================================
   DISPLAY STATUS
   ============================================================ */

$today=strtotime(
    date('Y-m-d')
);

foreach($loans as &$loan){

    $remaining=
        (float)$loan['remaining_balance'];

    $dueDate=
        strtotime($loan['due_date']);

    if($remaining<=0){

        $loan['display_status']=
            'completed';

    }elseif(
        $dueDate&&
        $dueDate<$today
    ){

        $loan['display_status']=
            'overdue';

    }else{

        $loan['display_status']=
            'active';
    }
}

unset($loan);

/* ============================================================
   SEARCH
   ============================================================ */

if($search!==''){

    $searchLower=
        strtolower($search);

    $loans=array_filter(
        $loans,
        function($loan)
        use($searchLower){

            $borrower=
                strtolower(
                    $loan['borrower_name']??''
                );

            $reference=
                strtolower(
                    $loan['reference_number']??''
                );

            $account=
                strtolower(
                    $loan['account_name']??''
                );

            $collateral=
                strtolower(
                    $loan['item_name']??''
                );

            return
                strpos(
                    $borrower,
                    $searchLower
                )!==false||
                strpos(
                    $reference,
                    $searchLower
                )!==false||
                strpos(
                    $account,
                    $searchLower
                )!==false||
                strpos(
                    $collateral,
                    $searchLower
                )!==false;
        }
    );
}

/* ============================================================
   STATUS FILTER
   ============================================================ */

if(in_array(
    $statusFilter,
    [
        'active',
        'overdue',
        'completed'
    ],
    true
)){

    $loans=array_filter(
        $loans,
        fn($loan)=>
            $loan['display_status']===
            $statusFilter
    );
}

/* ============================================================
   SORT
   ============================================================ */

usort(
    $loans,
    function($a,$b)
    use($sort){

        return match($sort){

            'oldest'=>
                strtotime(
                    $a['created_at']
                )<=>
                strtotime(
                    $b['created_at']
                ),

            'amount_asc'=>
                (float)$a['principal_amount']<=>
                (float)$b['principal_amount'],

            'amount_desc'=>
                (float)$b['principal_amount']<=>
                (float)$a['principal_amount'],

            'due_asc'=>
                strtotime(
                    $a['due_date']
                )<=>
                strtotime(
                    $b['due_date']
                ),

            'due_desc'=>
                strtotime(
                    $b['due_date']
                )<=>
                strtotime(
                    $a['due_date']
                ),

            default=>
                strtotime(
                    $b['created_at']
                )<=>
                strtotime(
                    $a['created_at']
                )
        };
    }
);

/* ============================================================
   STATISTICS
   ============================================================ */

$totalLoans=count($loans);

$activeLoans=0;
$overdueLoans=0;
$completedLoans=0;

$totalPrincipal=0;
$totalRemaining=0;
$totalPaid=0;

foreach($loans as $loan){

    $totalPrincipal+=
        (float)$loan['principal_amount'];

    $totalRemaining+=max(
        0,
        (float)$loan['remaining_balance']
    );

    $totalPaid+=
        (float)$loan['total_paid'];

    if(
        $loan['display_status']===
        'active'
    ){

        $activeLoans++;

    }elseif(
        $loan['display_status']===
        'overdue'
    ){

        $overdueLoans++;

    }elseif(
        $loan['display_status']===
        'completed'
    ){

        $completedLoans++;
    }
}

/* ============================================================
   BORROWERS
   ============================================================ */

$borrowersStmt=$pdo->prepare("
    SELECT
        id,
        first_name,
        last_name
    FROM loan_borrowers
    WHERE business_id=?
    AND created_by=?
    ORDER BY first_name ASC,last_name ASC
");

$borrowersStmt->execute([
    $businessId,
    $userId
]);

$borrowers=
    $borrowersStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

/* ============================================================
   FUNDING ACCOUNTS
   ============================================================ */

$accountsStmt=$pdo->prepare("
    SELECT
        id,
        account_name,
        balance
    FROM loan_accounts
    WHERE business_id=?
    ORDER BY account_name ASC
");

$accountsStmt->execute([
    $businessId
]);

$accounts=
    $accountsStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta
name="viewport"
content="width=device-width,initial-scale=1.0">

<title>
<?=htmlspecialchars($pageTitle)?>
</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
rel="stylesheet">

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
rel="stylesheet">

<script>
(function(){
    const savedTheme=
        localStorage.getItem('bs-theme')||'light';

    document.documentElement
        .setAttribute(
            'data-bs-theme',
            savedTheme
        );
})();
</script>

<style>

body{
    font-size:.9rem;
}

.loan-stat,
.loan-card,
.filter-card{
    border:0;
    border-radius:14px;
}

.loan-stat{
    transition:transform .15s ease;
}

.loan-stat:hover{
    transform:translateY(-2px);
}

.loan-table th{
    font-size:.7rem;
    letter-spacing:.04em;
    white-space:nowrap;
}

.loan-table td{
    vertical-align:middle;
}

.status-badge{
    font-size:.68rem;
    font-weight:700;
    padding:5px 8px;
    border-radius:6px;
}

.money-box{
    border-radius:10px;
}

.installment-option{
    position:relative;
    cursor:pointer;
}

.installment-option input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.installment-box{
    border:1px solid var(--bs-border-color);
    border-radius:12px;
    padding:12px;
    background:var(--bs-body-bg);
    transition:all .15s ease;
}

.installment-option input:checked+
.installment-box{
    border-color:var(--bs-success);
    background:rgba(
        var(--bs-success-rgb),
        .08
    );
}

.installment-circle{
    width:22px;
    height:22px;
    min-width:22px;
    border:2px solid var(--bs-secondary-color);
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
}

.installment-option input:checked+
.installment-box
.installment-circle{
    border-color:var(--bs-success);
    background:var(--bs-success);
}

.installment-circle i{
    color:#fff;
    font-size:13px;
    display:none;
}

.installment-option input:checked+
.installment-box
.installment-circle i{
    display:block;
}

.installment-paid{
    opacity:.65;
    cursor:not-allowed;
}

.payment-type-card{
    border:2px solid var(--bs-border-color);
    border-radius:12px;
    padding:15px;
    cursor:pointer;
    transition:.15s ease;
}

.payment-type-card:hover{
    border-color:var(--bs-primary);
}

.payment-type-radio{
    position:absolute;
    opacity:0;
}

.payment-type-radio:checked+
.payment-type-card{
    border-color:var(--bs-primary);
    background:rgba(
        var(--bs-primary-rgb),
        .08
    );
}

</style>

</head>

<body
class="bg-body-tertiary"
style="min-height:100vh">

<div
class="d-flex flex-column flex-lg-row"
style="min-height:100vh">

<?php
include __DIR__.
'/../../../resources/partials/loansidebar.php';
?>

<div
class="p-3 p-md-4 flex-grow-1 bg-body-tertiary overflow-hidden">

<!-- HEADER -->

<div
class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

<div>

<h2 class="fw-bold text-body mb-1">
Loans
</h2>

<p class="text-muted small mb-0">
Manage your loans, payments and schedules for
<span class="fw-bold text-primary">
<?=htmlspecialchars(
    $_SESSION['business_name']??''
)?>
</span>
</p>

</div>

<button
type="button"
class="btn btn-primary fw-bold px-3 py-2 rounded-3 shadow-sm"
data-bs-toggle="modal"
data-bs-target="#issueLoanModal">

<i class="bi bi-plus-circle-fill me-1"></i>
Issue New Loan

</button>

</div>

<?php if($success): ?>

<div class="alert alert-success border-0 shadow-sm py-2 small">

<i class="bi bi-check-circle-fill me-1"></i>

<?=htmlspecialchars($success)?>

</div>

<?php endif; ?>

<?php if($error): ?>

<div class="alert alert-danger border-0 shadow-sm py-2 small">

<i class="bi bi-exclamation-circle-fill me-1"></i>

<?=htmlspecialchars($error)?>

</div>

<?php endif; ?>

<!-- STATISTICS -->

<div class="row g-3 mb-4">

<div class="col-6 col-xl-3">

<div class="card loan-stat shadow-sm bg-body h-100">

<div class="card-body p-3">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small">
My Loans
</div>

<div class="fs-5 fw-bold">
<?=number_format($totalLoans)?>
</div>

</div>

<div
class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
style="width:38px;height:38px">

<i class="bi bi-file-earmark-text"></i>

</div>

</div>

</div>

</div>

</div>

<div class="col-6 col-xl-3">

<div class="card loan-stat shadow-sm bg-body h-100">

<div class="card-body p-3">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small">
Active
</div>

<div class="fs-5 fw-bold text-success">
<?=number_format($activeLoans)?>
</div>

</div>

<div
class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
style="width:38px;height:38px">

<i class="bi bi-clock"></i>

</div>

</div>

</div>

</div>

</div>

<div class="col-6 col-xl-3">

<div class="card loan-stat shadow-sm bg-body h-100">

<div class="card-body p-3">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small">
Overdue
</div>

<div class="fs-5 fw-bold text-danger">
<?=number_format($overdueLoans)?>
</div>

</div>

<div
class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
style="width:38px;height:38px">

<i class="bi bi-exclamation-triangle"></i>

</div>

</div>

</div>

</div>

</div>

<div class="col-6 col-xl-3">

<div class="card loan-stat shadow-sm bg-body h-100">

<div class="card-body p-3">

<div class="d-flex justify-content-between">

<div>

<div class="text-muted small">
Remaining
</div>

<div class="fs-5 fw-bold text-primary">
₱<?=number_format(
    $totalRemaining,
    2
)?>
</div>

</div>

<div
class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center"
style="width:38px;height:38px">

<i class="bi bi-cash-stack"></i>

</div>

</div>

</div>

</div>

</div>

</div>

<!-- FILTER -->

<div class="card filter-card shadow-sm bg-body mb-4">

<div class="card-body p-3">

<form
method="GET"
class="row g-2 align-items-end">

<input
type="hidden"
name="page"
value="loans">

<div class="col-12 col-md-5">

<label
class="form-label small fw-semibold text-muted">

Search

</label>

<input
type="text"
name="search"
value="<?=htmlspecialchars($search)?>"
class="form-control"
placeholder="Borrower, reference, account...">

</div>

<div class="col-6 col-md-3">

<label
class="form-label small fw-semibold text-muted">

Status

</label>

<select
name="status"
class="form-select">

<option value="all">
All Status
</option>

<option
value="active"
<?=$statusFilter==='active'?'selected':''?>>

Active

</option>

<option
value="overdue"
<?=$statusFilter==='overdue'?'selected':''?>>

Overdue

</option>

<option
value="completed"
<?=$statusFilter==='completed'?'selected':''?>>

Paid / Completed

</option>

</select>

</div>

<div class="col-6 col-md-3">

<label
class="form-label small fw-semibold text-muted">

Sort

</label>

<select
name="sort"
class="form-select">

<option
value="newest"
<?=$sort==='newest'?'selected':''?>>

Newest First

</option>

<option
value="oldest"
<?=$sort==='oldest'?'selected':''?>>

Oldest First

</option>

<option
value="amount_desc"
<?=$sort==='amount_desc'?'selected':''?>>

Amount: High to Low

</option>

<option
value="amount_asc"
<?=$sort==='amount_asc'?'selected':''?>>

Amount: Low to High

</option>

<option
value="due_asc"
<?=$sort==='due_asc'?'selected':''?>>

Due Date: Earliest

</option>

<option
value="due_desc"
<?=$sort==='due_desc'?'selected':''?>>

Due Date: Latest

</option>

</select>

</div>

<div class="col-12 col-md-1">

<button
type="submit"
class="btn btn-primary w-100">

<i class="bi bi-funnel"></i>

</button>

</div>

</form>

</div>

</div>

<!-- DESKTOP TABLE -->

<div
class="d-none d-md-block card border-0 shadow-sm rounded-4 overflow-hidden">

<div class="table-responsive">

<table
class="table table-hover align-middle mb-0 loan-table">

<thead class="table-light">

<tr>

<th class="ps-4 py-3">
Reference
</th>

<th>
Borrower
</th>

<th>
Type
</th>

<th>
Principal
</th>

<th>
Payment
</th>

<th>
Remaining
</th>

<th>
Frequency
</th>

<th>
Due Date
</th>

<th>
Status
</th>

<th class="text-end pe-4">
Action
</th>

</tr>

</thead>

<tbody>

<?php if(empty($loans)): ?>

<tr>

<td
colspan="10"
class="text-center py-5 text-muted">

<i class="bi bi-search display-6 opacity-50"></i>

<div class="fw-semibold mt-2">
No loans found
</div>

</td>

</tr>

<?php else: ?>

<?php foreach($loans as $loan):

$status=
    $loan['display_status'];

$isCompleted=
    $status==='completed';

$isOverdue=
    $status==='overdue';

if($isCompleted){

    $statusClass='success';
    $statusText='Paid';

}elseif($isOverdue){

    $statusClass='danger';
    $statusText='Overdue';

}else{

    $statusClass='primary';
    $statusText='Active';
}

$frequencyText=[
    'daily'=>'Daily',
    'weekly'=>'Weekly',
    'biweekly'=>'Bi-weekly',
    'monthly'=>'Monthly'
];

$frequency=
    $frequencyText[
        $loan['payment_frequency']??
        'monthly'
    ]??'Monthly';

$paymentType=
    $loan['payment_type']??
    'installment';

?>

<tr>

<td class="ps-4">

<div class="fw-semibold">

<?=htmlspecialchars(
    $loan['reference_number']?:'—'
)?>

</div>

<div
class="text-muted"
style="font-size:.68rem">

#<?= (int)$loan['id']?>

</div>

</td>

<td>

<div class="fw-bold">

<?=htmlspecialchars(
    $loan['borrower_name']
)?>

</div>

<div
class="text-muted"
style="font-size:.7rem">

<?=htmlspecialchars(
    $loan['account_name']
)?>

</div>

</td>

<td>

<?php if($paymentType==='lump_sum'): ?>

<span class="badge bg-warning text-dark">
Lump Sum
</span>

<?php else: ?>

<span class="badge bg-primary">
Installment
</span>

<?php endif; ?>

</td>

<td>

<div class="fw-semibold">

₱<?=number_format(
    $loan['principal_amount'],
    2
)?>

</div>

<div
class="text-muted"
style="font-size:.68rem">

Total:
₱<?=number_format(
    $loan['total_payable'],
    2
)?>

</div>

</td>

<td>

<div class="fw-bold text-primary">

₱<?=number_format(
    $loan['fixed_payment_amount']??0,
    2
)?>

</div>

<div
class="text-muted"
style="font-size:.68rem">

<?=$paymentType==='lump_sum'
    ?'Full Payment'
    :htmlspecialchars($frequency)?>

</div>

</td>

<td>

<div class="fw-bold text-success">

₱<?=number_format(
    max(
        0,
        $loan['remaining_balance']
    ),
    2
)?>

</div>

<div
class="text-muted"
style="font-size:.68rem">

Paid:
₱<?=number_format(
    $loan['total_paid'],
    2
)?>

</div>

</td>

<td>

<?php if(
    $paymentType==='lump_sum'
): ?>

<span class="badge bg-warning text-dark">
Lump Sum
</span>

<?php else: ?>

<span
class="badge bg-primary bg-opacity-10 text-primary">

<?=htmlspecialchars($frequency)?>

</span>

<?php endif; ?>

</td>

<td>

<div
class="<?=$isOverdue?'text-danger fw-bold':''?>">

<?=date(
    'M d, Y',
    strtotime($loan['due_date'])
)?>

</div>

</td>

<td>

<span
class="status-badge bg-<?=$statusClass?> bg-opacity-10 text-<?=$statusClass?>">

<?=$statusText?>

</span>

</td>

<td class="text-end pe-4">

<div
class="d-flex justify-content-end gap-1">

<?php if(!$isCompleted): ?>

<button
type="button"
class="btn btn-sm btn-success"
data-bs-toggle="modal"
data-bs-target="#paymentModal<?=$loan['id']?>">

<i class="bi bi-cash-stack"></i>

</button>

<?php endif; ?>

<a
href="index.php?page=loan_details&id=<?=(int)$loan['id']?>"
class="btn btn-sm btn-light border">

<i class="bi bi-eye"></i>

</a>

</div>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>

</div>

<!-- MOBILE -->

<div class="d-block d-md-none">

<?php if(empty($loans)): ?>

<div
class="card shadow-sm border-0 rounded-4 text-center py-5">

<i
class="bi bi-file-earmark-text display-5 text-muted opacity-50">
</i>

<h6 class="fw-bold mt-2">
No loans found
</h6>

</div>

<?php else: ?>

<div class="d-flex flex-column gap-3">

<?php foreach($loans as $loan):

$status=
    $loan['display_status'];

$isCompleted=
    $status==='completed';

$isOverdue=
    $status==='overdue';

if($isCompleted){

    $statusClass='success';
    $statusText='Paid';

}elseif($isOverdue){

    $statusClass='danger';
    $statusText='Overdue';

}else{

    $statusClass='primary';
    $statusText='Active';
}

$frequencyText=[
    'daily'=>'Daily',
    'weekly'=>'Weekly',
    'biweekly'=>'Bi-weekly',
    'monthly'=>'Monthly'
];

$frequency=
    $frequencyText[
        $loan['payment_frequency']??
        'monthly'
    ]??'Monthly';

$paymentType=
    $loan['payment_type']??
    'installment';

?>

<div class="card loan-card shadow-sm bg-body">

<div class="card-body p-3">

<div
class="d-flex justify-content-between mb-3">

<div>

<div class="small text-muted">

<?=htmlspecialchars(
    $loan['reference_number']?:
    'No Reference'
)?>

</div>

<h6 class="fw-bold mb-0">

<?=htmlspecialchars(
    $loan['borrower_name']
)?>

</h6>

</div>

<span
class="status-badge bg-<?=$statusClass?> bg-opacity-10 text-<?=$statusClass?>">

<?=$statusText?>

</span>

</div>

<div class="mb-3">

<?php if(
    $paymentType==='lump_sum'
): ?>

<span class="badge bg-warning text-dark">
Lump Sum / Full Payment
</span>

<?php else: ?>

<span class="badge bg-primary">
Installment / <?=$frequency?>
</span>

<?php endif; ?>

</div>

<div class="row g-2 mb-3">

<div class="col-6">

<div class="money-box bg-body-tertiary p-2">

<div
class="text-muted"
style="font-size:.68rem">

Principal

</div>

<div class="fw-bold">

₱<?=number_format(
    $loan['principal_amount'],
    2
)?>

</div>

</div>

</div>

<div class="col-6">

<div class="money-box bg-body-tertiary p-2">

<div
class="text-muted"
style="font-size:.68rem">

Remaining

</div>

<div class="fw-bold text-success">

₱<?=number_format(
    max(
        0,
        $loan['remaining_balance']
    ),
    2
)?>

</div>

</div>

</div>

<div class="col-6">

<div
class="money-box bg-primary bg-opacity-10 p-2">

<div
class="text-muted"
style="font-size:.68rem">

Payment

</div>

<div class="fw-bold text-primary">

₱<?=number_format(
    $loan['fixed_payment_amount']??0,
    2
)?>

</div>

</div>

</div>

<div class="col-6">

<div class="money-box bg-body-tertiary p-2">

<div
class="text-muted"
style="font-size:.68rem">

Type

</div>

<div class="fw-bold">

<?=$paymentType==='lump_sum'
    ?'Lump Sum'
    :'Installment'?>

</div>

</div>

</div>

</div>

<div
class="d-flex justify-content-between small mb-2">

<span class="text-muted">

Paid:

<strong class="text-body">

₱<?=number_format(
    $loan['total_paid'],
    2
)?>

</strong>

</span>

<span class="text-muted">

Total:

<strong class="text-body">

₱<?=number_format(
    $loan['total_payable'],
    2
)?>

</strong>

</span>

</div>

<div class="border-top pt-2">

<div
class="d-flex justify-content-between small">

<span class="text-muted">

Issued:
<?=date(
    'M d, Y',
    strtotime($loan['loan_date'])
)?>

</span>

<span
class="<?=$isOverdue?'text-danger fw-bold':'text-muted'?>">

Due:
<?=date(
    'M d, Y',
    strtotime($loan['due_date'])
)?>

</span>

</div>

</div>

<div
class="d-flex justify-content-end gap-2 mt-3">

<?php if(!$isCompleted): ?>

<button
type="button"
class="btn btn-sm btn-success fw-semibold"
data-bs-toggle="modal"
data-bs-target="#paymentModal<?=$loan['id']?>">

<i class="bi bi-cash-stack me-1"></i>

Payment

</button>

<?php endif; ?>

<a
href="index.php?page=loan_details&id=<?=(int)$loan['id']?>"
class="btn btn-sm btn-light border fw-semibold">

<i class="bi bi-eye me-1"></i>

Details

</a>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<!-- ========================================================
     PAYMENT MODALS
     ======================================================== -->

<?php foreach($loans as $loan):

if($loan['display_status']==='completed'){
    continue;
}

$loanId=(int)$loan['id'];

$schedules=
    $loanSchedules[$loanId]??[];

$remainingBalance=max(
    0,
    (float)$loan['remaining_balance']
);

$paymentType=
    $loan['payment_type']??
    'installment';

$isLumpSum=
    $paymentType==='lump_sum';

?>

<div
class="modal fade"
id="paymentModal<?=$loanId?>"
tabindex="-1">

<div
class="modal-dialog modal-dialog-centered modal-lg">

<div
class="modal-content border-0 shadow-lg rounded-4">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i
class="bi bi-cash-coin text-success me-2">
</i>

Record Payment

</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<form
method="POST"
class="payment-form"
data-loan-id="<?=$loanId?>"
data-payment-type="<?=$paymentType?>"
data-has-schedules="<?=empty($schedules)?'0':'1'?>"
data-remaining="<?=$remainingBalance?>">

<input
type="hidden"
name="record_payment"
value="1">

<input
type="hidden"
name="loan_id"
value="<?=$loanId?>">

<div class="modal-body">

<div class="mb-3">

<div class="small text-muted">
Borrower
</div>

<strong>
<?=htmlspecialchars(
    $loan['borrower_name']
)?>
</strong>

</div>

<div
class="p-3 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-25 mb-3">

<div class="small text-muted">
Remaining Loan Balance
</div>

<div class="fs-4 fw-bold text-success">

₱<?=number_format(
    $remainingBalance,
    2
)?>

</div>

</div>

<?php if($isLumpSum): ?>

<!-- ======================================================
     LUMP SUM PAYMENT
     ====================================================== -->

<div class="alert alert-warning small">

<i class="bi bi-info-circle me-1"></i>

This is a <strong>Lump Sum</strong> loan.
You can enter the full payment or any partial amount.

Example:
₱5,500 loan → borrower pays ₱500 →
remaining balance becomes ₱5,000.

</div>

<div class="mb-3">

<label
class="form-label fw-bold">

Payment Amount (₱)
<span class="text-danger">*</span>

</label>

<div class="input-group input-group-lg">

<span class="input-group-text">
₱
</span>

<input
type="number"
step="0.01"
min="0.01"
max="<?=$remainingBalance?>"
name="custom_payment_amount"
class="form-control custom-payment-input"
placeholder="Enter amount"
required>

</div>

<div class="form-text">

Maximum payment:
₱<?=number_format(
    $remainingBalance,
    2
)?>

</div>

</div>

<?php else: ?>

<!-- ======================================================
     INSTALLMENT PAYMENT
     ====================================================== -->

<div class="mb-3">

<div
class="d-flex justify-content-between align-items-center mb-2">

<div>

<label class="form-label fw-bold mb-0">

Select Installments to Pay

</label>

<div class="text-muted small">

You can select multiple installments.

</div>

</div>

<?php if(!empty($schedules)): ?>

<button
type="button"
class="btn btn-sm btn-outline-primary select-all-installments"
data-loan-id="<?=$loanId?>">

Select All

</button>

<?php endif; ?>

</div>

<?php if(empty($schedules)): ?>

<div class="alert alert-warning small">

No payment schedule was found.

You can enter a custom payment amount below.

</div>

<?php else: ?>

<div
class="d-flex flex-column gap-2"
id="scheduleList<?=$loanId?>">

<?php foreach(
    $schedules
    as $index=>$schedule
):

$scheduleId=
    (int)$schedule['id'];

$scheduleStatus=
    $schedule['status']??
    'unpaid';

$amountDue=round(
    (float)$schedule['amount_due'],
    2
);

$isPaid=
    $scheduleStatus==='paid';

$isPartiallyPaid=
    $scheduleStatus==='partially_paid';

$statusLabel=
    $isPaid
    ?'Paid'
    :(
        $isPartiallyPaid
        ?'Partially Paid'
        :'Unpaid'
    );

$statusClass=
    $isPaid
    ?'success'
    :(
        $isPartiallyPaid
        ?'warning'
        :'primary'
    );

?>

<label
class="installment-option <?=$isPaid?'installment-paid':''?>">

<input
type="checkbox"
name="schedule_ids[]"
value="<?=$scheduleId?>"
data-amount="<?=number_format(
    $amountDue,
    2,
    '.',
    ''
)?>"
<?=$isPaid?'disabled':''?>>

<div class="installment-box">

<div
class="d-flex align-items-center gap-3">

<div class="installment-circle">

<i class="bi bi-check"></i>

</div>

<div class="flex-grow-1">

<div
class="d-flex justify-content-between">

<div>

<div class="fw-bold">

Installment <?=$index+1?>

</div>

<div class="small text-muted">

Due:
<?=date(
    'M d, Y',
    strtotime(
        $schedule['due_date']
    )
)?>

</div>

</div>

<div class="text-end">

<div class="fw-bold">

₱<?=number_format(
    $amountDue,
    2
)?>

</div>

<span
class="badge bg-<?=$statusClass?> bg-opacity-10 text-<?=$statusClass?>">

<?=$statusLabel?>

</span>

</div>

</div>

</div>

</div>

</div>

</label>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

<?php if(!empty($schedules)): ?>

<div
class="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 mb-3">

<div
class="d-flex justify-content-between">

<div>

<div class="small text-muted">
Selected Installments
</div>

<div
class="fw-bold"
id="selectedCount<?=$loanId?>">

0 installments

</div>

</div>

<div class="text-end">

<div class="small text-muted">
Payment Amount
</div>

<div
class="fs-4 fw-bold text-primary"
id="selectedTotal<?=$loanId?>">

₱0.00

</div>

</div>

</div>

</div>

<?php endif; ?>

<div class="mb-3">

<label
class="form-label fw-semibold small">

Or Enter Custom Payment Amount

</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
type="number"
step="0.01"
min="0.01"
max="<?=$remainingBalance?>"
name="custom_payment_amount"
class="form-control custom-payment-input"
placeholder="Optional partial payment">

</div>

<div class="form-text">

Use this if the borrower pays only part of an installment.

</div>

</div>

<?php endif; ?>

<div class="mb-3">

<label
class="form-label fw-semibold small">

Payment Date

</label>

<input
type="date"
name="payment_date"
class="form-control"
value="<?=date('Y-m-d')?>"
required>

</div>

<div>

<label
class="form-label fw-semibold small">

Notes / Remarks

</label>

<textarea
name="payment_notes"
class="form-control"
rows="2"
placeholder="Optional payment remarks..."></textarea>

</div>

</div>

<div class="modal-footer">

<button
type="button"
class="btn btn-light"
data-bs-dismiss="modal">

Cancel

</button>

<button
type="submit"
class="btn btn-success fw-bold save-payment-btn"
<?=(!$isLumpSum&&!empty($schedules))
?'disabled':''?>>

<i class="bi bi-check-circle me-1"></i>

Save Payment

</button>

</div>

</form>

</div>

</div>

</div>

<?php endforeach; ?>

<!-- ========================================================
     ISSUE LOAN MODAL
     ======================================================== -->

<div
class="modal fade"
id="issueLoanModal"
tabindex="-1">

<div
class="modal-dialog modal-dialog-centered modal-lg">

<div
class="modal-content border-0 shadow-lg rounded-4">

<div class="modal-header">

<h5 class="modal-title fw-bold">

<i
class="bi bi-file-earmark-plus-fill text-primary me-2">
</i>

Issue New Loan

</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<form
method="POST"
enctype="multipart/form-data">

<input
type="hidden"
name="issue_loan"
value="1">

<div class="modal-body">

<!-- BORROWER + ACCOUNT -->

<div class="row">

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Borrower
<span class="text-danger">*</span>

</label>

<select
name="borrower_id"
class="form-select"
required>

<option value="">
-- Choose Borrower --
</option>

<?php foreach($borrowers as $b): ?>

<option
value="<?=(int)$b['id']?>">

<?=htmlspecialchars(
    $b['first_name'].
    ' '.
    $b['last_name']
)?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Funding Account / Wallet
<span class="text-danger">*</span>

</label>

<select
name="account_id"
class="form-select"
required>

<option value="">
-- Choose Account --
</option>

<?php foreach(
    $accounts as $acc
): ?>

<option
value="<?=(int)$acc['id']?>">

<?=htmlspecialchars(
    $acc['account_name']
)?>
—
₱<?=number_format(
    $acc['balance'],
    2
)?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<!-- PAYMENT TYPE -->

<div class="mb-4">

<label
class="form-label fw-bold">

Payment Type
<span class="text-danger">*</span>

</label>

<div class="row g-3">

<div class="col-md-6">

<input
type="radio"
name="payment_type"
value="installment"
id="paymentTypeInstallment"
class="payment-type-radio"
checked>

<label
for="paymentTypeInstallment"
class="payment-type-card d-block">

<div
class="d-flex align-items-center gap-3">

<div
class="rounded-circle bg-primary bg-opacity-10 text-primary p-2">

<i class="bi bi-calendar2-week fs-4"></i>

</div>

<div>

<div class="fw-bold">
Installment
</div>

<div class="small text-muted">

Borrower pays according to a
daily, weekly, bi-weekly or monthly schedule.

</div>

</div>

</div>

</label>

</div>

<div class="col-md-6">

<input
type="radio"
name="payment_type"
value="lump_sum"
id="paymentTypeLumpSum"
class="payment-type-radio">

<label
for="paymentTypeLumpSum"
class="payment-type-card d-block">

<div
class="d-flex align-items-center gap-3">

<div
class="rounded-circle bg-warning bg-opacity-10 text-warning p-2">

<i class="bi bi-cash-coin fs-4"></i>

</div>

<div>

<div class="fw-bold">
Lump Sum
</div>

<div class="small text-muted">

Borrower pays the loan in full on the due date,
but partial payments are allowed.

</div>

</div>

</div>

</label>

</div>

</div>

</div>

<!-- REFERENCE + DATE -->

<div class="row">

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Reference Number
<span class="text-muted">
(Optional)
</span>

</label>

<input
type="text"
name="reference_number"
class="form-control"
placeholder="e.g. LOAN-2026-001">

</div>

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Loan Date
<span class="text-danger">*</span>

</label>

<input
type="date"
name="loan_date"
id="loanDate"
class="form-control"
value="<?=date('Y-m-d')?>"
required>

</div>

</div>

<!-- AMOUNT -->

<div class="row">

<div class="col-md-4 mb-3">

<label
class="form-label fw-semibold small">

Principal Amount (₱)
<span class="text-danger">*</span>

</label>

<input
type="number"
step="0.01"
min="0.01"
name="principal_amount"
id="principalAmount"
class="form-control"
required
placeholder="5000.00">

</div>

<div class="col-md-4 mb-3">

<label
class="form-label fw-semibold small">

Interest Rate (%)

</label>

<input
type="number"
step="0.01"
min="0"
name="interest_rate"
id="interestRate"
class="form-control"
value="0"
placeholder="10">

</div>

<div class="col-md-4 mb-3">

<label
class="form-label fw-semibold small">

Loan Term

</label>

<div class="input-group">

<input
type="number"
min="1"
name="term_value"
id="termValue"
class="form-control"
value="4"
required>

<select
name="term_unit"
id="termUnit"
class="form-select">

<option value="days">
Days
</option>

<option
value="months"
selected>

Months

</option>

</select>

</div>

</div>

</div>

<!-- FREQUENCY -->

<div
class="row"
id="installmentOptions">

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Payment Frequency
<span class="text-danger">*</span>

</label>

<select
name="payment_frequency"
id="paymentFrequency"
class="form-select">

<option value="daily">
Daily
</option>

<option value="weekly">
Weekly
</option>

<option value="biweekly">
Bi-weekly
</option>

<option
value="monthly"
selected>

Monthly

</option>

</select>

<div class="form-text">

Choose how often the borrower should pay.

</div>

</div>

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Fixed Payment Amount

</label>

<div class="input-group">

<span class="input-group-text">
₱
</span>

<input
type="number"
step="0.01"
id="fixedPaymentPreview"
class="form-control"
readonly>

</div>

</div>

</div>

<!-- LUMP SUM INFO -->

<div
id="lumpSumInfo"
class="alert alert-warning d-none">

<i class="bi bi-info-circle me-1"></i>

<strong>Lump Sum Loan:</strong>

The borrower will have one full-payment due date.

Partial payments are allowed.

For example:

<strong>
₱5,500 total → ₱500 paid → ₱5,000 remaining.
</strong>

</div>

<!-- PREVIEW -->

<div
class="p-3 rounded-3 bg-body-tertiary border mb-4">

<div class="row g-3">

<div class="col-6 col-md-3">

<div class="small text-muted">
Total Interest
</div>

<div
class="fw-bold"
id="previewInterest">

₱0.00

</div>

</div>

<div class="col-6 col-md-3">

<div class="small text-muted">
Total Payable
</div>

<div
class="fw-bold text-primary"
id="previewTotal">

₱0.00

</div>

</div>

<div class="col-6 col-md-3">

<div class="small text-muted">
Number of Payments
</div>

<div
class="fw-bold"
id="previewPayments">

0

</div>

</div>

<div class="col-6 col-md-3">

<div class="small text-muted">
Due Date
</div>

<div
class="fw-bold text-primary"
id="displayDueDate">

--

</div>

</div>

</div>

</div>

<hr class="my-4 opacity-25">

<!-- COLLATERAL -->

<h6 class="fw-bold mb-3">

<i
class="bi bi-shield-check text-success me-2">
</i>

Collateral

<span
class="text-muted fw-normal small">

(Optional)

</span>

</h6>

<div class="row">

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Item Name / Title

</label>

<input
type="text"
name="collateral_item"
class="form-control"
placeholder="e.g. Smartphone, Jewelry, OR/CR">

</div>

<div class="col-md-6 mb-3">

<label
class="form-label fw-semibold small">

Estimated Value (₱)

</label>

<input
type="number"
step="0.01"
min="0"
name="collateral_value"
class="form-control">

</div>

</div>

<div class="row">

<div class="col-md-7 mb-3">

<label
class="form-label fw-semibold small">

Description / Specs / Serial #

</label>

<textarea
name="collateral_description"
class="form-control"
rows="3"></textarea>

</div>

<div class="col-md-5 mb-3">

<label
class="form-label fw-semibold small">

Collateral Photo

</label>

<input
type="file"
name="collateral_image"
class="form-control"
accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

<div class="form-text">

JPG, PNG or WEBP

</div>

</div>

</div>

</div>

<div class="modal-footer">

<button
type="button"
class="btn btn-light fw-semibold"
data-bs-dismiss="modal">

Cancel

</button>

<button
type="submit"
class="btn btn-primary fw-bold">

<i class="bi bi-check-circle me-1"></i>

Issue Loan

</button>

</div>

</form>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

<script>

document.addEventListener(
'DOMContentLoaded',
function(){

/* ==========================================================
   PAYMENT TYPE
   ========================================================== */

const paymentTypeInstallment=
    document.getElementById(
        'paymentTypeInstallment'
    );

const paymentTypeLumpSum=
    document.getElementById(
        'paymentTypeLumpSum'
    );

const installmentOptions=
    document.getElementById(
        'installmentOptions'
    );

const lumpSumInfo=
    document.getElementById(
        'lumpSumInfo'
    );

function updatePaymentType(){

    const lumpSum=
        paymentTypeLumpSum?.checked;

    if(lumpSum){

        installmentOptions?.classList.add(
            'd-none'
        );

        lumpSumInfo?.classList.remove(
            'd-none'
        );

    }else{

        installmentOptions?.classList.remove(
            'd-none'
        );

        lumpSumInfo?.classList.add(
            'd-none'
        );
    }

    calculateLoanPreview();
}

paymentTypeInstallment?.addEventListener(
    'change',
    updatePaymentType
);

paymentTypeLumpSum?.addEventListener(
    'change',
    updatePaymentType
);

/* ==========================================================
   PAYMENT FORMS
   ========================================================== */

document.querySelectorAll(
    '.payment-form'
).forEach(function(form){

    const loanId=
        form.dataset.loanId;

    const paymentType=
        form.dataset.paymentType;

    const checkboxes=
        form.querySelectorAll(
            'input[name="schedule_ids[]"]'
        );

    const totalDisplay=
        document.getElementById(
            'selectedTotal'+loanId
        );

    const countDisplay=
        document.getElementById(
            'selectedCount'+loanId
        );

    const saveButton=
        form.querySelector(
            '.save-payment-btn'
        );

    const selectAllButton=
        form.querySelector(
            '.select-all-installments'
        );

    const customInput=
        form.querySelector(
            '.custom-payment-input'
        );

    function updatePaymentTotal(){

        let total=0;
        let count=0;

        checkboxes.forEach(
            function(checkbox){

                if(
                    checkbox.checked&&
                    !checkbox.disabled
                ){

                    total+=
                        parseFloat(
                            checkbox.dataset.amount
                        )||0;

                    count++;
                }
            }
        );

        total=
            Math.round(
                total*100
            )/100;

        if(totalDisplay){

            totalDisplay.textContent=
                '₱'+
                total.toLocaleString(
                    'en-PH',
                    {
                        minimumFractionDigits:2,
                        maximumFractionDigits:2
                    }
                );
        }

        if(countDisplay){

            countDisplay.textContent=
                count+
                (
                    count===1
                    ?' installment'
                    :' installments'
                );
        }

        if(
            paymentType==='installment'&&
            checkboxes.length>0
        ){

            if(customInput?.value){

                saveButton.disabled=
                    parseFloat(
                        customInput.value
                    )<=0;

            }else{

                saveButton.disabled=
                    count===0;
            }
        }

        if(selectAllButton){

            const available=
                Array.from(
                    checkboxes
                ).filter(
                    c=>!c.disabled
                );

            const selected=
                available.filter(
                    c=>c.checked
                );

            if(
                available.length>0&&
                selected.length===
                available.length
            ){

                selectAllButton.textContent=
                    'Clear All';

                selectAllButton.classList
                    .remove(
                        'btn-outline-primary'
                    );

                selectAllButton.classList
                    .add(
                        'btn-outline-danger'
                    );

            }else{

                selectAllButton.textContent=
                    'Select All';

                selectAllButton.classList
                    .remove(
                        'btn-outline-danger'
                    );

                selectAllButton.classList
                    .add(
                        'btn-outline-primary'
                    );
            }
        }
    }

    checkboxes.forEach(
        function(checkbox){

            checkbox.addEventListener(
                'change',
                function(){

                    if(customInput){
                        customInput.value='';
                    }

                    updatePaymentTotal();
                }
            );
        }
    );

    selectAllButton?.addEventListener(
        'click',
        function(){

            const available=
                Array.from(
                    checkboxes
                ).filter(
                    c=>!c.disabled
                );

            const allSelected=
                available.length>0&&
                available.every(
                    c=>c.checked
                );

            available.forEach(
                function(c){

                    c.checked=
                        !allSelected;
                }
            );

            if(customInput){
                customInput.value='';
            }

            updatePaymentTotal();
        }
    );

    customInput?.addEventListener(
        'input',
        function(){

            if(this.value){

                checkboxes.forEach(
                    function(c){

                        c.checked=false;
                    }
                );
            }

            const val=
                parseFloat(
                    this.value
                )||0;

            const max=
                parseFloat(
                    form.dataset.remaining
                )||0;

            saveButton.disabled=
                !(
                    val>0&&
                    val<=max
                );

            updatePaymentTotal();
        }
    );

    form.addEventListener(
        'submit',
        function(event){

            const max=
                parseFloat(
                    form.dataset.remaining
                )||0;

            let paymentAmount=0;

            if(customInput?.value){

                paymentAmount=
                    parseFloat(
                        customInput.value
                    )||0;

            }else{

                checkboxes.forEach(
                    function(c){

                        if(
                            c.checked&&
                            !c.disabled
                        ){

                            paymentAmount+=
                                parseFloat(
                                    c.dataset.amount
                                )||0;
                        }
                    }
                );
            }

            paymentAmount=
                Math.round(
                    paymentAmount*100
                )/100;

            if(
                paymentAmount<=0||
                paymentAmount>max
            ){

                event.preventDefault();

                alert(
                    'Please enter a valid payment amount up to ₱'+
                    max.toFixed(2)
                );

                return;
            }

            if(!confirm(
                'Record payment of ₱'+
                paymentAmount.toLocaleString(
                    'en-PH',
                    {
                        minimumFractionDigits:2,
                        maximumFractionDigits:2
                    }
                )+
                '?'
            )){

                event.preventDefault();
            }
        }
    );

    updatePaymentTotal();
});

/* ==========================================================
   LOAN PREVIEW
   ========================================================== */

function calculateLoanPreview(){

    const principal=
        parseFloat(
            document.getElementById(
                'principalAmount'
            )?.value
        )||0;

    const interestRate=
        parseFloat(
            document.getElementById(
                'interestRate'
            )?.value
        )||0;

    const termValue=
        parseInt(
            document.getElementById(
                'termValue'
            )?.value
        )||0;

    const termUnit=
        document.getElementById(
            'termUnit'
        )?.value||
        'months';

    const frequency=
        document.getElementById(
            'paymentFrequency'
        )?.value||
        'monthly';

    const lumpSum=
        document.getElementById(
            'paymentTypeLumpSum'
        )?.checked;

    const interest=
        principal*
        (interestRate/100);

    const total=
        principal+
        interest;

    let payments=1;

    if(lumpSum){

        payments=1;

    }else{

        if(termUnit==='months'){

            if(frequency==='daily'){

                payments=
                    termValue*30;

            }else if(
                frequency==='weekly'
            ){

                payments=
                    termValue*4;

            }else if(
                frequency==='biweekly'
            ){

                payments=
                    termValue*2;

            }else{

                payments=
                    termValue;
            }

        }else{

            if(frequency==='daily'){

                payments=
                    termValue;

            }else if(
                frequency==='weekly'
            ){

                payments=
                    Math.ceil(
                        termValue/7
                    );

            }else if(
                frequency==='biweekly'
            ){

                payments=
                    Math.ceil(
                        termValue/14
                    );

            }else{

                payments=
                    Math.ceil(
                        termValue/30
                    );
            }
        }

        payments=
            Math.max(
                1,
                payments
            );
    }

    const fixedPayment=
        total/payments;

    const previewInterest=
        document.getElementById(
            'previewInterest'
        );

    const previewTotal=
        document.getElementById(
            'previewTotal'
        );

    const previewPayments=
        document.getElementById(
            'previewPayments'
        );

    const fixedPaymentPreview=
        document.getElementById(
            'fixedPaymentPreview'
        );

    if(previewInterest){

        previewInterest.textContent=
            '₱'+
            interest.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits:2,
                    maximumFractionDigits:2
                }
            );
    }

    if(previewTotal){

        previewTotal.textContent=
            '₱'+
            total.toLocaleString(
                'en-PH',
                {
                    minimumFractionDigits:2,
                    maximumFractionDigits:2
                }
            );
    }

    if(previewPayments){

        previewPayments.textContent=
            payments;
    }

    if(fixedPaymentPreview){

        fixedPaymentPreview.value=
            fixedPayment.toFixed(2);
    }

    calculateDueDate();
}

/* ==========================================================
   DUE DATE
   ========================================================== */

function calculateDueDate(){

    const loanDate=
        document.getElementById(
            'loanDate'
        )?.value;

    const termValue=
        parseInt(
            document.getElementById(
                'termValue'
            )?.value
        )||0;

    const termUnit=
        document.getElementById(
            'termUnit'
        )?.value||
        'months';

    const display=
        document.getElementById(
            'displayDueDate'
        );

    if(
        !display||
        !loanDate||
        termValue<=0
    ){

        if(display){
            display.textContent='--';
        }

        return;
    }

    const parts=
        loanDate.split('-');

    const date=
        new Date(
            parseInt(parts[0]),
            parseInt(parts[1])-1,
            parseInt(parts[2])
        );

    if(termUnit==='months'){

        date.setMonth(
            date.getMonth()+
            termValue
        );

    }else{

        date.setDate(
            date.getDate()+
            termValue
        );
    }

    display.textContent=
        date.toLocaleDateString(
            'en-US',
            {
                year:'numeric',
                month:'short',
                day:'numeric'
            }
        );
}

/* ==========================================================
   PREVIEW EVENTS
   ========================================================== */

[
    'principalAmount',
    'interestRate',
    'termValue',
    'termUnit',
    'paymentFrequency',
    'loanDate'
].forEach(
    function(id){

        const element=
            document.getElementById(id);

        if(!element){
            return;
        }

        element.addEventListener(
            'input',
            calculateLoanPreview
        );

        element.addEventListener(
            'change',
            calculateLoanPreview
        );
    }
);

calculateLoanPreview();

});
</script>

</body>
</html>