<?php
session_start();
include('config.php');

$booking_id = intval($_GET['booking_id'] ?? 0);

// Fetch booking details
$stmt = $conn->prepare("SELECT b.*, u.name as provider_name, uc.name as client_name, uc.email as client_email 
                        FROM bookings b 
                        JOIN users u ON b.provider_id = u.id 
                        JOIN users uc ON b.client_id = uc.id 
                        WHERE b.id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Invalid booking reference.");
}

// Official PayFast Sandbox Credentials
$merchant_id = '10049466';
$merchant_key = 'mwq0of8ftp92s';
$passphrase = 'amandlaskills';
$payfast_url = 'https://sandbox.payfast.co.za/eng/process';

$domain = "https://amandlaskills.infinityfree.me"; 

// Gateway Callback URLs
$return_url = $domain . "/processEscrow.php?booking_id=" . $booking_id;
$cancel_url = $domain . "/paymentDetails.php?booking_id=" . $booking_id;
$notify_url = $domain . "/itn.php";

$amount = number_format($booking['quoted_price'], 2, '.', '');
$item_name = "Work Description: " . $booking['work_description'];
$name_first = htmlspecialchars($booking['client_name']);
$email_address = htmlspecialchars($booking['client_email']);

$data = array(
    'merchant_id' => $merchant_id,
    'merchant_key' => $merchant_key,
    'return_url' => $return_url,
    'cancel_url' => $cancel_url,
    'notify_url' => $notify_url,
    'name_first' => $name_first,
    'email_address' => $email_address,
    'm_payment_id' => $booking_id,
    'amount' => $amount,
    'item_name' => $item_name
);

// generate signature
$pfOutput = '';
foreach( $data as $key => $val ) {
    if($val !== '') {
        $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';
    }
}
$getString = substr( $pfOutput, 0, -1 );
if( $passphrase !== null ) {
    $getString .= '&passphrase='. urlencode( trim( $passphrase ) );
}
$signature = md5( $getString );
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment | Amandla Skills</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payment-card { border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-light">
    <div class="color: #000; font-weight: bold; text-align: center; padding: 5px 0; font-size: 0.8rem; letter-spacing: 1px; text-transform: uppercase;"> PayFast Sandbox Environment Active </div>
    
    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card payment-card p-4 border-0">
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <h4 class="fw-bold mb-0">Secure Payment</h4>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-5 bg-light p-4 rounded-4">
                            <h6 class="fw-bold text-uppercase small text-muted mb-3">Order Summary</h6>
                            <p class="small text-muted mb-1">Job:</p>
                            <p class="fw-bold"><?php echo htmlspecialchars($booking['work_description']); ?></p>
                            <p class="small text-muted mb-1">Provider:</p>
                            <p class="fw-bold mb-4"><?php echo htmlspecialchars($booking['provider_name']); ?></p>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0">Total:</h5>
                                <h4 class="fw-bold mb-0" style="color: #7c3aed;">R <?php echo $amount; ?></h4>
                            </div>
                            <small class="text-muted d-block mt-3">Funds are held securely in escrow until the job is completed.</small>
                        </div>

                        <div class="col-md-7 ps-md-4 d-flex flex-column justify-content-center">
                            <div class="alert alert-warning rounded-4 border-0 mb-4 small text-dark">
                                <strong>Testing Mode:</strong> You will be redirected to the PayFast gateway. Use the test cards provided on the next screen. No real money will be deducted.
                            </div>
                            
                            <!-- The Official PayFast Form -->
                            <form action="<?php echo $payfast_url; ?>" method="POST">
                                <?php foreach($data as $name => $value): ?>
                                    <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                                <?php endforeach; ?>
                                <input type="hidden" name="signature" value="<?php echo $signature; ?>">

                                <button type="submit" class="btn text-white w-100 fw-bold py-3 rounded-pill" style="background-color:#7c3aed;">
                                    Proceed to PayFast Checkout
                                </button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <a href="clientDashboard.php" class="text-muted text-decoration-none small">Cancel and return to dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>