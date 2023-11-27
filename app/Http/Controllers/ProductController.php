<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    //
    public function index(Request $request)
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout()
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
        $products = Product::all();
        $lineItems = [];
        $total_price = 0;
        foreach ($products as $product) {
            $total_price += $product->price;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        'images' => [$product->image]
                    ],
                    'unit_amount' => $product->price * 100,
                ],
                'quantity' => 1,
            ];
        };
        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true)
        ]);

        $order = new Order();
        $order->status = 'unpaid';
        $order->total_price = $total_price;
        $order->session_id = $checkout_session->id;
        $order->save();

        return redirect($checkout_session->url);
    }

    public function success(Request $request)
    {

        Stripe::setApiKey(env('STRIPE_SECRET'));
        $sessionId = $request->get('session_id');
        // info($sessionId);
        $session = Session::retrieve($sessionId);
        // if (!$session) {
        //     throw new NotFoundHttpException;
        // }
        // $customer = Customer::retrieve($session->customer);
        // return view('product.checkout-success', compact($customer));

        $order = Order::where('session_id', $session->id)->where('status', 'unpaid')->first();
        if (!$order) {
            throw new NotFoundHttpException();
        }
        $order->status = 'paid';
        $order->save();
        return view('product.checkout-success');
    }

    public function cancel()
    {
    }

    public function webhook()
    {
        

        // The library needs to be configured with your account's secret key.
        // Ensure the key is kept out of any version control system you might be using.
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        // This is your Stripe CLI webhook secret for testing your endpoint locally.
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            info($e);

            return response('', 400);
            // http_response_code(400);
            // exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            info($e);
            return response('', 400);

            // http_response_code(400);
            // exit();
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        // http_response_code(200);
        return response('');
    }
}
