<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Transaction;
use App\Models\TransactionDetail;

use Exception;

use Veritrans\Snap;
use Veritrans_Config;
use Veritrans_VtWeb;


class CheckoutController extends Controller
{
    public function process(Request $request) {
        // Save User Data
        $user = Auth::user();
        $user->update($request->except('total_price'));

        // Proses Checkout
        $code = 'STORE-' . mt_rand(00000,99999);
        $carts = Cart::with(['product','user'])
                    ->where('users_id', Auth::user()->id)
                    ->get();
        
        //Transaction Create
        $transaction = Transaction::create([
            'users_id' => Auth::user()->id,
            'insurance_price' => 0,
            'shipping_price' => 0,
            'total_price' => (int) $request->total_price,
            'transaction_status' => 'PENDING',
            'code' => $code
        ]); 

        foreach ($carts as $cart) {
            $trx = 'TRX-' . mt_rand(00000,99999);
            TransactionDetail::create([
                'transaction_id' => $transaction->id,
                'products_id' => $cart->product->id,
                'price' => $cart->product->price,
                'shipping_status' => 'PENDING',
                'resi' => '',
                'code' => $trx
            ]);
        }

        // Delete Card Data
        Cart::where('users_id', Auth::user()->id)->delete();
        
        //Konfigurasi Midtrans
        // Set your Merchant Server Key
        Veritrans_Config::$serverKey = config('services.midtrans.serverKey');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        Veritrans_Config::$isProduction = config('services.midtrans.isProduction');
        // Set sanitization on (default)
        Veritrans_Config::$isSanitized = config('services.midtrans.isSanitized');
        // Set 3DS transaction for credit card to true
        Veritrans_Config::$is3ds = config('services.midtrans.is3ds');

        //Buat Array Untuk Dikirim Ke Midtrans
        $midtrans = [
            'transaction_details' => [
                'order_id' => $code,
                'gross_amount' => (int) $request->total_price
            ],
            'customer_details' => [
                'first_name' => Auth::user()->name,
                'email' => Auth::user()->email,
            ],
            'enabled_payments' => [
                "gopay"
            ],
            'vtweb' => []
        ];

        try {
            // Redirect to Veritrans VTWeb page
            // header('Location: ' . Veritrans_Vtweb::getRedirectionUrl($midtrans));
            $paymentUrl = Veritrans_Vtweb::getRedirectionUrl($midtrans);
            return redirect($paymentUrl);
        }
        catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function callback(Request $request) {

    }
}
