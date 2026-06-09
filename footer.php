<!-- code for the footer, included on all pages -->
<?php
include('lang.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
 $lang = $_SESSION['lang'];
 
?>

<link href="footer.css" rel="stylesheet">

<footer class="mt-3 bt-1 border-secondary">
    <div class="container text-center py-4">
        <p class="footer-content fw-bold mb-3"><?php echo $translations[$lang]['footer_quote']; ?></p>
        
        <div class="footer-contact-details  p-3 d-inline-block ">
            <p class="footer-contact fw-bold mb-1"><?php echo $translations[$lang]['contact']; ?></p>
            
            <p class="footer-contact mb-1">Phone number: <a href="tel:+27212222626" class="text-decoration-none text-purple">+27 21 222 2626</a></p>
            
            <p class="footer-contact mb-0">Email: <a href="mailto:info@amandlaskills.com?subject=Inquiry:%20Amandla%20Skills" class="text-decoration-none text-purple">info@amandlaskills.com</a></p>
        </div>
        
        <p class="mt-4 text-muted small">Powered by Chabula Capital</p>
        <p class="mt-4 text-muted small ">&copy; <?php echo date("Y"); ?> Amandla Skills</p>
    </div>
</footer>