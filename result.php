<?
//
// Это обработчик Result для Вашего платежа
// Перепишите методы process_success() и process_fail() в SimplePay.class.php
//

require_once("SimplePay.class.php");

$simplepay = new SimplePay;
$simplepay->process_request_result();
?>
