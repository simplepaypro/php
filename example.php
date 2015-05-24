<?
require_once('SimplePay.class.php');

$new_payment = new SimplePay_Payment;               // Новый экземпляр платежа
$new_payment->order_id = 300;	                    // номер заказа	
$new_payment->amount = 500;		            // сумма
$new_payment->description = 'Описание заказа';

$simplepay = new SimplePay;

// 1. Обычный платеж с передачей параметров в браузере пользователя
$simplepay->init_payment($new_payment);

// 2. Прямое взаимодействие - вернет ссылку для оплаты и описание параметров в виде XML или JSON
$direct_answer = $simplepay->init_payment_direct($new_payment);

// Переадресация плательщика
header("location: ".$direct_answer['sp_redirect_url']);

/* вернет массив типа
(
    [sp_salt] => 872014043
    [sp_status] => ok
    [sp_redirect_url_type] => need data
    [sp_redirect_url] => https://secure.simplepay.pro/payment_page.php?id=e96081c978845e3410d1a9ebf1c8c4ef
    [sp_sig] => 09a1505a52db8d5d9c14ab298df4a120
)
*/

// 3. проведение платежа по рекурентному профилю
$answer = $simplepay->make_recurring_payment($new_payment,10);
/* вернет массив вида:
(
    [sp_status] => need_payment
    [sp_payment_url] => https://secure.simplepay.pro/pay_transaction.php?id=S05YbFRvdEk1Q1l2OGpXQ2V6NGpyZz09
)
*/

// 4. проверка состояния платежа
$answer = $simplepay->get_payment_status_by_transaction_id(170);

/* вернет массив вида:
(
    [sp_salt] => 77178
    [sp_status] => ok
    [sp_payment_id] => 170
    [sp_create_date] => 2015-01-06 03:32:04
    [sp_payment_system] => TEST
    [sp_transaction_status] => EXECUTED
    [sp_sig] => 592cad6da470243f771e413abbf0555d
)
*/
?>
