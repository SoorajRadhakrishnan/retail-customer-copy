<?php

namespace App\Http\Controllers\Counter;

use App\Models\Admin\Item;
use App\Models\Admin\Staff;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\Category;
use App\Models\Admin\Customer;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\Driver;
use App\Models\Admin\ItemPrice;
use App\Models\SaleOrderItems;
use App\Models\SaleOrderPayment;
use App\Models\SaleOrders;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $categorys = Category::where('branch_id', auth()->user()->branch_id)->withCount('items')->get();
        $categorys = Category::where('branch_id', auth()->user()->branch_id)->get();
        $items = Item::leftJoin('item_prices', function ($join) {
            $join->on('items.id', '=', 'item_prices.item_id');
        })->leftJoin('categories', function ($joins) {
            $joins->on('items.category_id', '=', 'categories.id');
        })->leftJoin('price_size', function ($joins) {
            $joins->on('item_prices.price_size_id', '=', 'price_size.id');
        })->where('items.branch_id', auth()->user()->branch_id)
            ->where('items.item_type', '1')
            ->where('items.active', 'yes')
            // ->where('item_prices.price', '>', 0)
            ->select(DB::raw('items.*,item_prices.id as price_id,item_prices.item_id,item_prices.price_size_id,item_prices.price,item_prices.stock as item_stock,item_prices.cost_price as item_price_cost_price,categories.category_slug,price_size.size_name'))
            ->get();
        // dd($items);
        $drivers = Driver::where('branch_id', auth()->user()->branch_id)->get();
        $sale_orders = $customer = null;
        if($request->customer)
        {
            $customer = Customer::where("id",$request->customer)->first();
        }
        return view("Counter.sale", compact('categorys', 'items', 'drivers', 'sale_orders','customer'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd($request->all());

        // echo "<pre>";print_r($request->all('gross_total', 'item_price', 'final_item_price', 'qty', 'discount_amount', 'total_price'));echo "</pre>";
        $data = $request->all();
        // Retrieve values from the request
        $discount = $data['discount'] ?? 0;
        $grossTotal = $data['gross_total'] ?? 0;
        $itemPrices = $data['item_price'] ?? [];
        $quantities = $data['qty'] ?? [];

        $finalItemPrices = [];
        $discountAmounts = [];
        $totalPrices = [];

        if ($discount > 0 && $grossTotal > 0) {
            $totalDiscountAllocated = 0;

            foreach ($itemPrices as $index => $itemPrice) {
                $qty = $quantities[$index] ?? 1;
                $itemTotalPrice = $itemPrice * $qty;

                // Proportional discount calculation
                $itemDiscount = ($itemTotalPrice / $grossTotal) * $discount;
                $itemDiscount = round($itemDiscount, 2);

                // Final item price after discount
                $finalItemPrice = $itemPrice - ($itemDiscount / $qty);
                $finalItemPrice = round($finalItemPrice, 2);

                // Update arrays
                $discountAmounts[$index] = $itemDiscount;
                $finalItemPrices[$index] = $finalItemPrice;
                $totalPrices[$index] = round($finalItemPrice * $qty, 2);

                $totalDiscountAllocated += $itemDiscount;
            }

            // Handle rounding differences
            if (round($totalDiscountAllocated, 2) !== round($discount, 2)) {
                $difference = round($discount - $totalDiscountAllocated, 2);
                $lastIndex = count($itemPrices) - 1;

                $discountAmounts[$lastIndex] += $difference;
                $finalItemPrices[$lastIndex] -= round($difference / $quantities[$lastIndex], 2);
                $totalPrices[$lastIndex] = round($finalItemPrices[$lastIndex] * $quantities[$lastIndex], 2);
            }

            $request->merge([
                'discount_amount' => $discountAmounts,
                'final_item_price' => $finalItemPrices,
                'total_price' => $totalPrices,
            ]);
        }

        // Update the request object

// echo "<pre>";print_r($request->all('gross_total', 'item_price', 'final_item_price', 'qty', 'discount_amount', 'total_price'));echo "</pre>";die;
        if ($request->sale_order_id) {
            if ($request->hold == '1') {

                $sale_insert = $this->getSaleOrderItemDetailsEdit($request->all());
                return redirect('home')->withMessage('Item added in Hold');
            }
            // echo '<pre>'; print_r($_POST);die;
            $sale_order_id = $request->sale_order_id;
            $amount_given = $request->amount_given;
            $payment_type = (isset($request->payment_type) && $request->payment_type != '') ? $request->payment_type : 'both';
            $order_type = (isset($request->order_type) && $request->order_type != '') ? $request->order_type : 'counter_sale';
            $card_num = (isset($request->card_num) && $request->card_num != '') ? $request->card_num : '0';

            //$customer_id = (isset($request->customer_id) && $request->customer_id !='') ? $request->customer_id : '';
            //$customer_uuid = (isset($request->customer_uuid) && $request->customer_uuid !='') ? $request->customer_uuid : '';
            $customer_name = (isset($request->customer_name) && $request->customer_name != '') ? $request->customer_name : '';
            $customer_number = (isset($request->customer_number) && $request->customer_number != '') ? $request->customer_number : '0';
            $customer_address = (isset($request->customer_address) && $request->customer_address != '') ? $request->customer_address : '';
            $customer_email = (isset($request->customer_email) && $request->customer_email != '') ? $request->customer_email : '';
            $customer_gender = (isset($request->customer_gender) && $request->customer_gender != '') ? $request->customer_gender : '';
            $discount = (isset($request->discount) && $request->discount != '') ? $request->discount : '0.0';
            $discount_per = (isset($request->discount_per) && $request->discount_per != '') ? $request->discount_per : '0.0';
            $staff_id = (isset($request->staff_id) && $request->staff_id != '') ? $request->staff_id : '0.0';

            $gross_total = (isset($request->gross_total) && $request->gross_total != '') ? $request->gross_total : 0;
            $tax_amount = (isset($request->tax_amount) && $request->tax_amount != '') ? $request->tax_amount : 0;
            $net_total = (isset($request->net_total) && $request->net_total != '') ? $request->net_total : 0;

            $cash = (isset($request->cash) && $request->cash != '') ? $request->cash : 0;
            $card = (isset($request->card) && $request->card != '') ? $request->card : 0;

            $payment_status = 'paid';
            $status = 'pending';

            $cus_details = array();
            $cus_details['customer_number'] = $customer_number;
            $cus_details['customer_name'] = $customer_name;
            $cus_details['customer_address'] = $customer_address;
            $cus_details['customer_gender'] = $customer_gender;
            $cus_details['customer_email'] = $customer_email;
            $customers = $this->getCustomerId($cus_details);
            $customer_id = $customers->id;
            $customer_uuid = $customers->uuid;

            $sale_insert = $this->getSaleOrderItemDetailsEdit($request->all());
            if ($sale_insert) {

                SaleOrders::where('id', $sale_order_id)->update([
                    'edit_staff_id' => $staff_id,
                    'card_num'  => $card_num,
                    'customer_name' => $customer_name,
                    'customer_number' => $customer_number,
                    'customer_address' => $customer_address,
                    'customer_email' => $customer_email,
                    'customer_gender' => $customer_gender,
                    'customer_id' => $customer_id,
                    'amount_given'  => $amount_given,
                    'payment_type'  => $payment_type,
                    'status'  => $status,
                    'payment_status'  => $payment_status,
                    'discount'  => $discount,
                    'discount_per' => $discount_per,
                    'without_tax'  => $gross_total,
                    'tax_amount'  => $tax_amount,
                    'with_tax'  => $net_total,
                    'order_type' => $order_type
                ]);
            }
            if ($payment_status == 'paid') {
                $request['user_id'] = auth()->user()->id;
                $request['shop_id'] = auth()->user()->branch_id;
                $request['customer_id'] = $customer_id;
                $this->multiPaymentInsert($request->all());
                // return redirect('print?id=' . $sale_order_id . '&re=home');
                return redirect('home')->with('print','yes')->with('print_id',$sale_order_id)->with('re','home');
            }
            // return redirect('print?id=' . $sale_order_id . '&re=home');
            return redirect('home')->with('print','yes')->with('print_id',$sale_order_id)->with('re','home');
        } else {
            $sale_insert = $this->getSaleOrderItemDetails($request->all());
            $sale_order_id = $sale_insert->id;
            $customer_id = $sale_insert->customer_id;
            $order_type = $sale_insert->order_type;
            $request['sale_order_id'] = $sale_order_id;
            $request['user_id'] = auth()->user()->id;
            $request['shop_id'] = auth()->user()->branch_id;
            $request['customer_id'] = $customer_id; //dd($request->all());
            $payment_status = (isset($request->payment_status) && $request->payment_status != '') ? $request->payment_status : '';
            if (($request->status != 'hold' || $payment_status == 'paid')) {
                $this->multiPaymentInsert($request->all());
                // return redirect('print?id=' . $sale_order_id . '&re=home');
                return redirect('home')->with('print','yes')->with('print_id',$sale_order_id)->with('re','home');
            }
            // return redirect('print?id=' . $sale_order_id . '&re=home');
            if($request->status == 'hold')
            {
                return redirect('home')->withMessage('Item added in Hold');
            }else{
                return redirect('home')->with('print','yes')->with('print_id',$sale_order_id)->with('re','home');
            }
        }
    }

    public function getCustomerId($inputs)
    {
        $customer_number = (isset($inputs['customer_number']) && $inputs['customer_number'] != '') ? $inputs['customer_number'] : '';
        $customer_name = (isset($inputs['customer_name']) && $inputs['customer_name'] != '') ? $inputs['customer_name'] : '';
        $customer_address = (isset($inputs['customer_address']) && $inputs['customer_address'] != '') ? $inputs['customer_address'] : '';
        $customer_email = (isset($inputs['customer_email']) && $inputs['customer_email'] != '') ? $inputs['customer_email'] : '';
        $customer_gender = (isset($inputs['customer_gender']) && $inputs['customer_gender'] != '') ? $inputs['customer_gender'] : '';
        $customer_id = '';

        $customer_number_result = Customer::where('customer_number', $customer_number)->first();

        if ($customer_number_result !== null) {

            Customer::where('customer_number', $customer_number)->update([
                'customer_name' => $customer_name,
                'customer_address' => $customer_address,
                'customer_email' => $customer_email,
                'customer_gender' => $customer_gender
            ]);
            $customers = Customer::where('customer_number', $customer_number)->first();
        } else {
            if ($customer_number != null) {

                $customers = Customer::create([
                    'customer_name' => $customer_name,
                    'customer_number' => $customer_number,
                    'customer_address' => $customer_address,
                    'customer_email' => $customer_email,
                    'customer_gender' => $customer_gender,
                    'branch_id' => auth()->user()->branch_id,
                    'uuid' => Str::uuid(),
                ]);
                $customers = Customer::where('customer_number', $customer_number)->first();
            }
        }
        return $customers;
    }

    public function getSaleOrderItemDetailsEdit($inputs)
    {

        $branch_id = auth()->user()->branch_id;
        $user_id = auth()->user()->id;
        $order_type = $inputs['order_type'];
        $sale_order_id = (isset($inputs['sale_order_id']) && $inputs['sale_order_id'] != '') ? $inputs['sale_order_id'] : '';
        $driver_id = (isset($inputs['driver_id']) && $inputs['driver_id'] != '') ? $inputs['driver_id'] : '0';
        $card_num = (isset($inputs['card_num']) && $inputs['card_num'] != '') ? $inputs['card_num'] : '';
        $vat = '';
        $payment_type = (isset($inputs['payment_type']) && $inputs['payment_type'] != '') ? $inputs['payment_type'] : '';
        $payment_status = (isset($inputs['payment_status']) && $inputs['payment_status'] != '') ? $inputs['payment_status'] : '';
        $status = (isset($inputs['status']) && $inputs['status'] != '') ? $inputs['status'] : '';
        $ordered_date = date("Y-m-d H:i:s");
        $customer_name = (isset($inputs['customer_name']) && $inputs['customer_name'] != '') ? $inputs['customer_name'] : '';
        $customer_number = (isset($inputs['customer_number']) && $inputs['customer_number'] != '') ? $inputs['customer_number'] : '0';
        $customer_address = (isset($inputs['customer_address']) && $inputs['customer_address'] != '') ? $inputs['customer_address'] : '';
        $amount_given = (isset($inputs['amount_given']) && $inputs['amount_given'] != '') ? $inputs['amount_given'] : '0';
        $balance_amount = (isset($inputs['balance_amount']) && $inputs['balance_amount'] != '') ? $inputs['balance_amount'] : '';

        $gross_total = (isset($inputs['gross_total']) && $inputs['gross_total'] != '') ? $inputs['gross_total'] : 0;
        $tax_amount = (isset($inputs['tax_amount']) && $inputs['tax_amount'] != '') ? $inputs['tax_amount'] : 0;
        $net_total = (isset($inputs['net_total']) && $inputs['net_total'] != '') ? $inputs['net_total'] : 0;

        $staff_id = (isset($inputs['staff_id']) && $inputs['staff_id'] != '') ? $inputs['staff_id'] : '0';

        $discount = (isset($inputs['discount']) && $inputs['discount'] != '') ? $inputs['discount'] : '0';

        SaleOrders::where('id', $sale_order_id)->update([
            'without_tax' => $gross_total,
            'tax_amount' => $tax_amount,
            'with_tax' => $net_total,
            'edit_staff_id' => $staff_id
        ]);

        $price_ids = $inputs['price_id'];
        $item_ids = $inputs['item_id'];
        $item_names = $inputs['item_name'];
        $item_prices = $inputs['item_price'];
        $qtys = $inputs['qty'];
        $final_item_prices = $inputs['final_item_price'];
        $item_stocks = $inputs['item_stock'];
        $tax_percents = $inputs['tax_percent'];
        $tax_amts = $inputs['tax_amt'];
        $tax_amt_not_rounds = $inputs['tax_amt_not_round'];
        $stock_applicables = $inputs['stock_applicable'];
        $discount_amounts = $inputs['discount_amount'];
        $discount_percents = $inputs['discount_percent'];
        $category_ids = $inputs['category_id'];
        $total_prices = $inputs['total_price'];
        $sale_order_item_ids = $inputs['sale_order_item_id'];
        $item_price_cost_prices = $inputs['item_price_cost_price'];
        $old_quantitys = $inputs['old_quantity'];

        $notess = (isset($inputs['notes']) && $inputs['notes'] != '') ? $inputs['notes'] : '';

        $total_amount = $without_tax = $tax_amount = $with_tax = 0;
        for ($i = 0; $i < count($item_ids); $i++) {
            $item_id = $item_ids[$i];
            $price_size_id = $price_ids[$i];
            $item_name = $item_names[$i];
            $qty = $qtys[$i];
            $unit_price = $item_prices[$i];
            $final_price = $final_item_prices[$i];
            $item_stock = $item_stocks[$i];
            $notes = (isset($notess[$i]) && $notess[$i] != '') ? $notess[$i] : '';

            $discount_percent = (isset($discount_percents[$i]) && $discount_percents[$i] != '') ? $discount_percents[$i] : 0;
            $discount_amount = (isset($discount_amounts[$i]) && $discount_amounts[$i] != '') ? $discount_amounts[$i] : 0;
            $tax_percentage = (isset($tax_percents[$i]) && $tax_percents[$i] != '') ? $tax_percents[$i] : 0;
            $tax_amt = (isset($tax_amts[$i]) && $tax_amts[$i] != '') ? $tax_amts[$i] : 0;
            $tax_amt_not_round = (isset($tax_amt_not_rounds[$i]) && $tax_amt_not_rounds[$i] != '') ? $tax_amt_not_rounds[$i] : 0;
            $stock_applicable = (isset($stock_applicables[$i]) && $stock_applicables[$i] != '') ? $stock_applicables[$i] : 0;
            $category_id = (isset($category_ids[$i]) && $category_ids[$i] != '') ? $category_ids[$i] : 0;
            $total_price = (isset($total_prices[$i]) && $total_prices[$i] != '') ? $total_prices[$i] : 0;
            $sale_order_item_id = (isset($sale_order_item_ids[$i]) && $sale_order_item_ids[$i] != '') ? $sale_order_item_ids[$i] : 0;
            $item_price_cost_price = (isset($item_price_cost_prices[$i]) && $item_price_cost_prices[$i] !== '') ? $item_price_cost_prices[$i] : NULL;
            $old_quantity = (isset($old_quantitys[$i]) && $old_quantitys[$i] != '') ? $old_quantitys[$i] : 0;
            if(strtolower($item_price_cost_price) == 'nan')
            {
                $item_price_cost_price = 0;
            }


            $item_details = Item::where('id', $item_id)->first();
            $item_id_i = $item_details->id;
            $other_item_name = $item_details->other_item_name;

            $item_price_i = $unit_price;
            // $cost_price_taken = "item"; //TODO:
            $tax_without_price = $unit_price;
            $tax_type = $item_details->tax_type;
            $tax_name = $item_details->tax_name;
            $tax_count = $item_details->multiple_tax_count;
            if ($item_id == 0) {
                $cost_price = 0;
            } else {
                if ("item" == 'purchase') { //TODO:
                    $cost_price = 0; //$item_details->cost_price;
                } else {
                    $cost_price = $item_details->cost_price;
                }
            }
            if(strtolower($cost_price) == 'nan')
            {
                $cost_price = 0;
            }
            $multiplle_val = $qty * $item_price_i;
            $total_amount += $multiplle_val;

            if($sale_order_item_id > 0){

                if($old_quantity > 0){
                    $result_stock = DB::table('item_prices')->where('id', $price_size_id)->whereNull('deleted_at')->first();
                    DB::table('item_prices')->where('id', $price_size_id)->update([
                        'stock' => $result_stock->stock + $old_quantity
                    ]);
                }

                DB::table('sale_order_items')->where('id', $sale_order_item_id)->update([
                    'sale_order_id' => $sale_order_id,
                    'category_id' => $category_id,
                    'item_id' => $item_id_i,
                    'price_size_id' => $price_size_id, //
                    'item_name' => $item_name,
                    'other_item_name' => $other_item_name, //
                    'price' => $item_price_i, // unit-price
                    'qty' => $qty,
                    'tax_without_price' => $tax_without_price,
                    // 'cost_price' => $cost_price,
                    'notes' => $notes,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amount,
                    'item_discount' => '0', //$item_discount,
                    'item_unit_price' => $final_price, //$item_unit_price,
                    'tax_percentage' => $tax_percentage,
                    'tax_amt' => $tax_amt,
                    'tax_amt_not_round' => $tax_amt_not_round, //
                    'tax_type' => getVat($branch_id)->vat, // $tax_type, //
                    'tax_name' => 'VAT', // $tax_name, //
                    'tax_count' => '1', // $tax_count, //
                    'total_price' => $total_price, // $tax_count, //
                    'cost_price' => $item_price_cost_price, // $tax_count, //
                    // 'cost_price_taken' => $cost_price_taken,
                ]);
            }else{
                $sale_order_item_id = DB::table('sale_order_items')->insertGetId([
                    'sale_order_id' => $sale_order_id,
                    'category_id' => $category_id,
                    'item_id' => $item_id_i,
                    'price_size_id' => $price_size_id, //
                    'item_name' => $item_name,
                    'other_item_name' => $other_item_name, //
                    'price' => $item_price_i, // unit-price
                    'qty' => $qty,
                    'tax_without_price' => $tax_without_price,
                    // 'cost_price' => $cost_price,
                    'notes' => $notes,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amount,
                    'item_discount' => '0', //$item_discount,
                    'item_unit_price' => $final_price, //$item_unit_price,
                    'tax_percentage' => $tax_percentage,
                    'tax_amt' => $tax_amt,
                    'tax_amt_not_round' => $tax_amt_not_round, //
                    'tax_type' => getVat($branch_id)->vat, // $tax_type, //
                    'tax_name' => 'VAT', // $tax_name, //
                    'tax_count' => '1', // $tax_count, //
                    'total_price' => $total_price, // $tax_count, //
                    'cost_price' => $item_price_cost_price, // $tax_count, //
                    // 'cost_price_taken' => $cost_price_taken,
                ]);
            }

            if ($stock_applicable == 1 && $status != 'hold') {
                if ($sale_order_item_id) {
                    $result_stock = DB::table('item_prices')->where('id', $price_size_id)->whereNull('deleted_at')->first();
                    //->where('branch_id', $branch_id)->where('item_id', $item_id_i)
                    $stock_reaming = 0;
                    $old_stock = $result_stock->stock;
                    $stock_reaming = $result_stock->stock - $qty;

                    if ($item_id > 0) {
                        if ($stock_reaming >= 0) {
                            DB::table('item_prices')->where('id', $price_size_id)->update(['stock' => $stock_reaming]);
                            //->where('branch_id', $branch_id)->where('item_id', $item_id_i)->whereNull('deleted_at')
                            $user_id = $user_id;
                            $item_id = $item_id_i;
                            $reference_no = $sale_order_id;
                            $reference_key = $order_type;
                            if ($status == 'hold') {
                                $reference_key = $reference_key . '-' . $status;
                            }
                            $action_type = 'sub';
                            $open_stock = $old_stock;
                            $stock_value = $qty;
                            $closing_stock = $stock_reaming;
                            // DB::enableQueryLog();
                            DB::table('stock_management_history')->insert([
                                'user_id' => $user_id,
                                'item_id' => $item_id,
                                'item_price_id' => $price_size_id,
                                'action_type' => $action_type,
                                'open_stock' => $open_stock,
                                'stock_value' => $stock_value,
                                'closing_stock' => $closing_stock,
                                'date_added' => $ordered_date,
                                'reference_no' => $reference_no,
                                'reference_key' => $reference_key,
                                'shop_id' =>  $branch_id
                            ]);
                            // dd(DB::getQueryLog());
                        }
                    }
                }
            }
        }
        return DB::table('sale_orders')->where('id', $sale_order_id)->first();
    }

    public function delivery_list()
    {
        $delivery_list = SaleOrders::where('order_type', 'delivery')->where('status', '!=', 'delivered')
            ->where('status', '!=', 'reject')->orderBy('id','DESC')->get();
        return view("Counter.Model.delivery", compact('delivery_list'));
    }

    public function hold_list()
    {
        $hold_list = SaleOrders::where('payment_status', 'unpaid')->where('status', 'hold')->get();
        return view("Counter.Model.hold", compact('hold_list'));
    }

    public function change_delivery_status(Request $request)
    {
        $sale_id = $request->sale_id;
        $type = $request->type;
        $status = $request->status; //dd($request->all());
        $payment_type = $request->payment_type; //dd($request->all());
        $total = $request->total; //dd($request->all());
        if ($sale_id) {
            if ($type == 'delete') {

                $items = SaleOrderItems::where('sale_order_id', $sale_id)->get();

                foreach($items as $key => $item)
                {
                    $old = ItemPrice::where('id', $item->price_size_id)->first();
                    $closing_stock = $old->stock + $item->qty;
                    ItemPrice::where('id', $item->price_size_id)->increment('stock', $item->qty);

                    DB::table('stock_management_history')->insert([
                        'user_id' => auth()->user()->id,
                        'item_id' => $item->item_id,
                        'item_price_id' => $item->price_size_id,
                        'action_type' => 'add',
                        'open_stock' => $old->stock,
                        'stock_value' => $item->qty,
                        'closing_stock' => $closing_stock,
                        'date_added' => date("Y-m-d H:i:s"),
                        'reference_no' => $item->id,
                        'reference_key' => "Delivery Delete",
                        'shop_id' =>  auth()->user()->branch_id
                    ]);
                }
                SaleOrders::where('id', $sale_id)->delete();
                SaleOrderItems::where('sale_order_id', $sale_id)->delete();
                SaleOrderPayment::where('sale_order_id', $sale_id)->delete();
            } elseif ($type == 'status') {
                SaleOrders::where('id', $sale_id)->update([
                    'status' => $status
                ]);
                $balance = $total - getPaidSaleAmount($sale_id)->amount;
                if($balance > 0)
                {
                    SaleOrderPayment::create([
                        'sale_order_id' => $sale_id,
                        'payment_type' => $payment_type,
                        'amount' => $balance,
                        'currency' => app('appSettings')['currency']->value,
                        'multiplier' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'real_amount' => $balance,
                        'sub_payment_type' => '', //$sub_multiple_payment_type,
                        'remarks' => '', //$multiple_payment_remarks,
                        'order_type' => 'delivery',
                        'user_id' => auth()->user()->id,
                        'shop_id' => auth()->user()->branch_id
                    ]);
                }
            }
            return 'success';
        } else {
            return 'failed';
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $uuid)
    {
        $categorys = Category::where('branch_id', auth()->user()->branch_id)->get();
        $items = Item::leftJoin('item_prices', function ($join) {
            $join->on('items.id', '=', 'item_prices.item_id');
        })->leftJoin('categories', function ($joins) {
            $joins->on('items.category_id', '=', 'categories.id');
        })->leftJoin('price_size', function ($joins) {
            $joins->on('item_prices.price_size_id', '=', 'price_size.id');
        })->where('items.branch_id', auth()->user()->branch_id)
            ->where('items.item_type', '1')
            ->where('items.active', 'yes')
            // ->where('item_prices.price', '>', 0)
            ->select(DB::raw('items.*,item_prices.id as price_id,item_prices.item_id,item_prices.price_size_id,item_prices.price,item_prices.stock as item_stock,categories.category_slug,price_size.size_name'))
            ->get();
        // dd($items);
        $drivers = Driver::where('branch_id', auth()->user()->branch_id)->get();

        // $sale_orders = SaleOrders::leftJoin('sale_order_items', function ($join) {
        //     $join->on('sale_orders.id', '=', 'sale_order_items.sale_order_id');
        // })->where('sale_orders.shop_id', auth()->user()->branch_id)
        //     ->where('sale_orders.uuid', $uuid)
        //     ->select(DB::raw('sale_orders.*,
        //         sale_order_items.id as sale_order_item_id,
        //         sale_order_items.item_id,
        //         sale_order_items.price_size_id,
        //         sale_order_items.price,
        //         sale_order_items.item_name,
        //         sale_order_items.qty,
        //         sale_order_items.tax_without_price'))
        //     ->get();

        $sale_orders = SaleOrders::where('uuid', $uuid)->first(); //dd($sale_orders);
        $customer = null;
        return view("Counter.sale", compact('categorys', 'items', 'drivers', 'sale_orders','customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function get_customer(Request $request)
    {
        // dd($request->keyword);
        $customer_number = $request->customer_number;
        if ($customer_number) {
            $data = Customer::where('customer_number', 'like', '%' . $customer_number . '%')->where('branch_id', auth()->user()->branch_id)->limit('5')->get();
            return response()->json(['data' => $data]);
        }
    }

    public function staff_pin_check(Request $request)
    {
        $pin_number = $request->pin_number;
        $data = ['status' => 'fail'];
        if ($pin_number) {
            $result = Staff::where('staff_pin', $pin_number)->where('branch_id', auth()->user()->branch_id)->first(['id', 'uuid', 'staff_name']);
            if ($result !== null) {
                $result['status'] = 'success';
                return response()->json($result);
            } else {
                return response()->json($data);
            }
        } else {
            return response()->json($data);
        }
    }

    public function item_search(Request $request)
    {
        $item_search = $request->search;
        if ($item_search) {

            $items = Item::leftJoin('item_prices', function ($join) {
                $join->on('items.id', '=', 'item_prices.item_id');
            })->leftJoin('categories', function ($joins) {
                $joins->on('items.category_id', '=', 'categories.id');
            })->leftJoin('price_size', function ($joins) {
                $joins->on('item_prices.price_size_id', '=', 'price_size.id');
            })->where('items.branch_id', auth()->user()->branch_id)
                ->where('items.item_type', '1')
                ->where('items.active', 'yes')
                // ->where('item_prices.price', '>', 0)
                ->select(DB::raw('items.*,item_prices.id as price_id,item_prices.item_id,item_prices.price_size_id,item_prices.price,item_prices.stock as item_stock,categories.category_slug,price_size.size_name'))
                ->where('items.item_name', 'like', '%' . $item_search . '%')
                ->orWhere('item_prices.barcode', 'like', '%' . $item_search . '%')
                ->limit('5')
                ->get();
            return response()->json(['data' => $items]);
        }
    }

    public function barcode_search(Request $request)
    {
        $item_search = $request->barcode;
        if ($item_search) {

            $items = Item::leftJoin('item_prices', function ($join) {
                $join->on('items.id', '=', 'item_prices.item_id');
            })->leftJoin('categories', function ($joins) {
                $joins->on('items.category_id', '=', 'categories.id');
            })->leftJoin('price_size', function ($joins) {
                $joins->on('item_prices.price_size_id', '=', 'price_size.id');
            })->where('items.branch_id', auth()->user()->branch_id)
                ->where('items.item_type', '1')
                ->where('items.active', 'yes')
                // ->where('item_prices.price', '>', 0)
                ->select(DB::raw('items.*,item_prices.id as price_id,item_prices.item_id,item_prices.price_size_id,item_prices.price,item_prices.stock as item_stock,categories.category_slug,price_size.size_name'))
                ->where('item_prices.barcode', $item_search)
                ->limit('1')
                ->get();
            return response()->json(['data' => $items]);
        }
    }

    public function print_file()
    {
        // return view('Counter.printhelper');
        return view('Counter.print');
    }

    public function multiPaymentInsert($inputs)
    {//dd("123");
        $pay_bill = $inputs['pay_bill'];
        $sale_order_id = $inputs['sale_order_id'];
        $net_total = $inputs['net_total'];
        $order_type = $inputs['order_type'];
        $user_id = $inputs['user_id'];
        $shop_id = $inputs['shop_id'];
        $current_date = date('Y-m-d H:i:s');
        $multiple_payment_amount = explode(',', $inputs['enter_amount']);
        // $multiple_payment_type = explode(',', $inputs['payment_id']);
        $check_amount = true;
        // echo '<pre>'; print_r($multiple_payment_amount);
        // echo '<pre>'; print_r($multiple_payment_type);
        $sale_payment_details = $this->get_sale_payment_types($sale_order_id);
        if (!$sale_payment_details->isEmpty()) {
            $current_date = $sale_payment_details[0]->created_at;
            DB::table('sale_order_payments')->where('sale_order_id', $sale_order_id)->delete();
            // mysqli_query($GLOBALS['conn'], "DELETE FROM `sale_order_payments` WHERE sale_order_id = '$sale_order_id'");
        }
        $i = $net_total;
        if (!empty($multiple_payment_amount) && ($multiple_payment_amount) != 0) {
            foreach ($multiple_payment_amount as $key => $value) {
                if ($value > 0) {
                    $payment_id = explode(',', $inputs['payment_id']);
                    $multiple_payment_type = isset($payment_id[$key]) ? $payment_id[$key] : '';
                    $multiple_payment_currency = app('appSettings')['currency']->value;
                    $multiplier = 1;

                    if ($value <= $i || ($i > 0)) {
                        $value_curr = $value;
                        if ($i > 0 && $value > $i) {
                            $value_curr = $i;
                        }

                        DB::table('sale_order_payments')->insertGetId([
                            'sale_order_id' => $sale_order_id,
                            'payment_type' => $multiple_payment_type,
                            'amount' => $value_curr,
                            'currency' => $multiple_payment_currency,
                            'multiplier' => $multiplier,
                            'created_at' => $current_date,
                            'real_amount' => $value,
                            'sub_payment_type' => '', //$sub_multiple_payment_type,
                            'remarks' => '', //$multiple_payment_remarks,
                            'order_type' => $order_type,
                            'user_id' => $user_id,
                            'shop_id' => $shop_id
                        ]);

                        if ($multiple_payment_type == 'credit') {
                            $cred_payment_details = $this->get_credit_sale($sale_order_id);
                            if (!$cred_payment_details->isEmpty()) {
                                $current_date = $cred_payment_details[0]->paid_date;
                                DB::table('credit_sale')->where('sale_order_id', $sale_order_id)->delete();
                                //mysqli_query($GLOBALS['conn'], "DELETE FROM `credit_sale` WHERE sale_order_id = '$sale_order_id'");
                            }
                            $customer_number = $inputs['customer_number'];
                            $customer_id = $inputs['customer_id'];
                            $customer_name = $inputs['customer_name'];
                            $order_type_uc = ucwords(str_replace("_", ' ', $order_type));

                            DB::table('credit_sale')->insertGetId([
                                'customer_id' => $customer_id,
                                'name' => $customer_name,
                                'number' => $customer_number,
                                'type' => 'credit',
                                'amount' => $value,
                                'paid_date' => $current_date,
                                'sale_order_id' => $sale_order_id,
                                'user_id' => $user_id,
                                'shop_id' => $shop_id
                            ]);
                        }

                        $i = $i - $value;
                    }
                }
            }
            $result_pay = DB::table('sale_order_payments')
                ->selectRaw('GROUP_CONCAT( payment_type ) as payment_type')
                ->where('sale_order_id', $sale_order_id)
                ->get(); //dd($result_pay);
            if (!$result_pay->isEmpty()) {
                $pay_type = $result_pay[0]->payment_type;
                if(!$pay_type == null){
                    DB::table('sale_orders')->where('id', $sale_order_id)->update(['payment_type' => $pay_type]);
                }
            }
        }
    }

    public function getSaleOrderItemDetails($inputs)
    {
        $branch_id = auth()->user()->branch_id;
        $user_id = auth()->user()->id;

        $order_type = $inputs['order_type'];
        $driver_id = (isset($inputs['driver_id']) && $inputs['driver_id'] != '') ? $inputs['driver_id'] : '0';
        $card_num = (isset($inputs['card_num']) && $inputs['card_num'] != '') ? $inputs['card_num'] : '0';

        $vat = '0';
        $payment_type = (isset($inputs['payment_type']) && $inputs['payment_type'] != '') ? $inputs['payment_type'] : '';
        $payment_status = (isset($inputs['payment_status']) && $inputs['payment_status'] != '') ? $inputs['payment_status'] : '';
        $status = (isset($inputs['status']) && $inputs['status'] != '') ? $inputs['status'] : 'pending';
        $ordered_date = date("Y-m-d H:i:s");
        $customer_uuid = (isset($inputs['customer_uuid']) && $inputs['customer_uuid'] != '') ? $inputs['customer_uuid'] : '';
        $customer_name = (isset($inputs['customer_name']) && $inputs['customer_name'] != '') ? $inputs['customer_name'] : '';
        $customer_email = (isset($inputs['customer_email']) && $inputs['customer_email'] != '') ? $inputs['customer_email'] : '';
        $customer_address = (isset($inputs['customer_address']) && $inputs['customer_address'] != '') ? $inputs['customer_address'] : '';
        $customer_number = (isset($inputs['customer_number']) && $inputs['customer_number'] != '') ? $inputs['customer_number'] : '0';
        $customer_gender = (isset($inputs['customer_gender']) && $inputs['customer_gender'] != '') ? $inputs['customer_gender'] : '';
        $amount_given = (isset($inputs['amount_given']) && $inputs['amount_given'] != '') ? $inputs['amount_given'] : '0';
        $balance_amount = (isset($inputs['balance_amount']) && $inputs['balance_amount'] != '') ? $inputs['balance_amount'] : '0';
        // $table_id = (isset($inputs['table_id']) && $inputs['table_id'] != '') ? $inputs['table_id'] : '0';
        // $floor_id = (isset($inputs['floor_id']) && $inputs['floor_id'] != '') ? $inputs['floor_id'] : '0';
        // $num_members = (isset($inputs['num_members']) && $inputs['num_members'] != '') ? $inputs['num_members'] : '0';
        $remarks = (isset($inputs['remarks']) && $inputs['remarks'] != '') ? $inputs['remarks'] : '';
        $date_time = (isset($inputs['date_time']) && $inputs['date_time'] != '') ? $inputs['date_time'] : '';
        $discount = (isset($inputs['discount']) && $inputs['discount'] != '') ? $inputs['discount'] : '0.0';
        $discount_per = (isset($inputs['discount_per']) && $inputs['discount_per'] != '') ? $inputs['discount_per'] : '0.0';
        $staff_id = (isset($inputs['staff_id']) && $inputs['staff_id'] != '') ? $inputs['staff_id'] : '0.0';

        //$cash_back_receipt_id = (isset($inputs['cash_back_receipt_id']) && $inputs['cash_back_receipt_id'] != '') ? $inputs['cash_back_receipt_id'] : '';
        //$cash_back_amount = (isset($inputs['cash_back_amount']) && $inputs['cash_back_amount'] != '') ? $inputs['cash_back_amount'] : '0';

        $credit = (isset($inputs['credit']) && $inputs['credit'] != '') ? $inputs['credit'] : '';

        $gross_total = (isset($inputs['gross_total']) && $inputs['gross_total'] != '') ? $inputs['gross_total'] : 0;
        $tax_amount = (isset($inputs['tax_amount']) && $inputs['tax_amount'] != '') ? $inputs['tax_amount'] : 0;
        $net_total = (isset($inputs['net_total']) && $inputs['net_total'] != '') ? $inputs['net_total'] : 0;
        //$cash_back_vat_amount = (isset($inputs['cash_back_vat_amount']) && $inputs['cash_back_vat_amount'] != '') ? $inputs['cash_back_vat_amount'] : 0;

        // $cash = (isset($inputs['cash']) && $inputs['cash'] != '') ? $inputs['cash'] : 0;
        // $card = (isset($inputs['card']) && $inputs['card'] != '') ? $inputs['card'] : 0;


        if ($credit == 'yes') {
            $payment_type = 'credit';
            $payment_status = 'paid';
        }

        $customer_id = '0';
        if ($customer_number > 0) {

            $customer_number_result = Customer::where('customer_number', $customer_number)->first();

            if ($customer_number_result !== null) {

                Customer::where('customer_number', $customer_number)->update([
                    'customer_name' => $customer_name,
                    'customer_address' => $customer_address,
                    'customer_email' => $customer_email,
                    'customer_gender' => $customer_gender
                ]);
                $customers = Customer::where('customer_number', $customer_number)->first();
                $customer_id = $customers->id;
                $customer_uuid = $customers->uuid; //TODO: customer post req geting value
            } else {
                if ($customer_number != null) {

                    $customers = Customer::create([
                        'customer_name' => $customer_name,
                        'customer_number' => $customer_number,
                        'customer_address' => $customer_address,
                        'customer_email' => $customer_email,
                        'customer_gender' => $customer_gender,
                        'branch_id' => auth()->user()->branch_id,
                        'uuid' => Str::uuid(),
                    ]);
                    $customer_id = $customers->id;
                    $customer_uuid = $customers->uuid; //TODO: customer post req geting value
                }
            }
        }
        $result = false;

        $result_date = DB::table('sale_orders')->where('id', $branch_id)->where('ordered_date', $ordered_date)
            ->whereNull('deleted_at')->first();
        if ($result_date == null) {
            $result = DB::table('sale_orders')->insertGetId([
                'uuid' => Str::uuid(),
                'shop_id' => $branch_id,
                'user_id' => $user_id,
                'order_type' => $order_type,
                'payment_type' => $payment_type,
                'discount' =>  $discount,
                'customer_id' => $customer_id,
                'customer_uuid' => $customer_uuid,
                'customer_name' => $customer_name,
                'customer_number' => $customer_number,
                'customer_address' => $customer_address,
                'customer_gender' => $customer_gender,
                'customer_email' => $customer_email,
                'driver_id' => $driver_id,
                'ordered_date' => $ordered_date,
                'payment_status' => $payment_status,
                'status' => $status,
                'amount_given' => $amount_given,
                'balance_amount' => $balance_amount,
                'remarks' => $remarks,
                'card_num' => $card_num,
                'vat' => $vat,
                'date_time' => $date_time,
                'discount_per' => $discount_per,
                'staff_id' => $staff_id,
                'without_tax' => $gross_total,
                'tax_amount' => $tax_amount,
                'with_tax' => $net_total,
                'receipt_id' => getNextReceiptId(),
                'created_at' => $ordered_date

            ]);
        }

        if ($result == true && $result_date == null) {
            $sale_order_id = $result;

            $price_ids = $inputs['price_id'];
            $item_ids = $inputs['item_id'];
            $item_names = $inputs['item_name'];
            $item_prices = $inputs['item_price'];
            $qtys = $inputs['qty'];
            $final_item_prices = $inputs['final_item_price'];
            $item_stocks = $inputs['item_stock'];
            $tax_percents = $inputs['tax_percent'];
            $tax_amts = $inputs['tax_amt'];
            $tax_amt_not_rounds = $inputs['tax_amt_not_round'];
            $stock_applicables = $inputs['stock_applicable'];
            $discount_amounts = $inputs['discount_amount'];
            $discount_percents = $inputs['discount_percent'];
            $category_ids = $inputs['category_id'];
            $total_prices = $inputs['total_price'];
            $item_price_cost_prices = $inputs['item_price_cost_price'];

            $notess = (isset($inputs['notes']) && $inputs['notes'] != '') ? $inputs['notes'] : '';

            $total_amount = $without_tax = $tax_amount = $with_tax = 0;
            for ($i = 0; $i < count($item_ids); $i++) {
                $item_id = $item_ids[$i];
                $price_size_id = $price_ids[$i];
                $item_name = $item_names[$i];
                $qty = $qtys[$i];
                $unit_price = $item_prices[$i];
                $final_price = $final_item_prices[$i];
                $item_stock = $item_stocks[$i];
                $notes = (isset($notess[$i]) && $notess[$i] != '') ? $notess[$i] : '';

                $discount_percent = (isset($discount_percents[$i]) && $discount_percents[$i] != '') ? $discount_percents[$i] : 0;
                $discount_amount = (isset($discount_amounts[$i]) && $discount_amounts[$i] != '') ? $discount_amounts[$i] : 0;
                $tax_percentage = (isset($tax_percents[$i]) && $tax_percents[$i] != '') ? $tax_percents[$i] : 0;
                $tax_amt = (isset($tax_amts[$i]) && $tax_amts[$i] != '') ? $tax_amts[$i] : 0;
                $tax_amt_not_round = (isset($tax_amt_not_rounds[$i]) && $tax_amt_not_rounds[$i] != '') ? $tax_amt_not_rounds[$i] : 0;
                $stock_applicable = (isset($stock_applicables[$i]) && $stock_applicables[$i] != '') ? $stock_applicables[$i] : 0;
                $category_id = (isset($category_ids[$i]) && $category_ids[$i] != '') ? $category_ids[$i] : 0;
                $total_price = (isset($total_prices[$i]) && $total_prices[$i] != '') ? $total_prices[$i] : 0;
                $item_price_cost_price = (isset($item_price_cost_prices[$i]) && $item_price_cost_prices[$i] != '') ? $item_price_cost_prices[$i] : 0;

                if(strtolower($item_price_cost_price) == 'nan')
                {
                    $item_price_cost_price = 0;
                }
                $item_details = Item::where('id', $item_id)->first();
                $item_id_i = $item_details->id;
                $other_item_name = $item_details->other_item_name;

                $item_price_i = $unit_price;
                // $cost_price_taken = "item"; //TODO:
                $tax_without_price = $unit_price;
                $tax_type = $item_details->tax_type;
                $tax_name = $item_details->tax_name;
                $tax_count = $item_details->multiple_tax_count;
                if ($item_id == 0) {
                    $cost_price = 0;
                } else {
                    if ("item" == 'purchase') { //TODO:
                        $cost_price = 0; //$item_details->cost_price;
                    } else {
                        $cost_price = $item_details->cost_price;
                    }
                }
                $multiplle_val = $qty * $item_price_i;
                $total_amount += $multiplle_val;
                // $end_time = date("Y-m-d H:i:s");
                // if ($item_details->show_time == 'yes') {
                //     $end_time = date("Y-m-d H:i:s", strtotime(date($ordered_date) . '+ ' . $qty . 'hours'));
                // }

                $sale_order_item_id = DB::table('sale_order_items')->insertGetId([
                    'sale_order_id' => $sale_order_id,
                    'category_id' => $category_id,
                    'item_id' => $item_id_i,
                    'price_size_id' => $price_size_id, //
                    'item_name' => $item_name,
                    'other_item_name' => $other_item_name, //
                    'price' => $item_price_i, // unit-price
                    'qty' => $qty,
                    'tax_without_price' => $tax_without_price,
                    'cost_price' => $cost_price,
                    'notes' => $notes,
                    'discount_percent' => $discount_percent,
                    'discount_amount' => $discount_amount,
                    'item_discount' => '0', //$item_discount,
                    'item_unit_price' => $final_price, //$item_unit_price,
                    'tax_percentage' => $tax_percentage,
                    'tax_amt' => $tax_amt,
                    'tax_amt_not_round' => $tax_amt_not_round, //
                    'tax_type' => getVat($branch_id)->vat, // $tax_type, //
                    'tax_name' => 'VAT', // $tax_name, //
                    'tax_count' => '1', // $tax_count, //
                    'total_price' => $total_price, // $tax_count, //
                    // 'cost_price' => $item_price_cost_price, // $tax_count, //
                    // 'cost_price_taken' => $cost_price_taken,
                ]);

                if ($stock_applicable == 1  && $status != 'hold') {
                    if ($sale_order_item_id) {
                        $result_stock = DB::table('item_prices')->where('id', $price_size_id)->whereNull('deleted_at')->first();
                        //->where('branch_id', $branch_id)->where('item_id', $item_id_i)
                        $stock_reaming = 0;
                        $old_stock = $result_stock->stock;
                        $stock_reaming = $result_stock->stock - $qty;

                        if ($item_id > 0) {
                            if ($stock_reaming >= 0) {
                                DB::table('item_prices')->where('id', $price_size_id)->update(['stock' => $stock_reaming]);
                                //->where('branch_id', $branch_id)->where('item_id', $item_id_i)->whereNull('deleted_at')
                                $user_id = $user_id;
                                $item_id = $item_id_i;
                                $reference_no = $sale_order_id;
                                $reference_key = $order_type;
                                if ($status == 'hold') {
                                    $reference_key = $reference_key . '-' . $status;
                                }
                                $action_type = 'sub';
                                $open_stock = $old_stock;
                                $stock_value = $qty;
                                $closing_stock = $stock_reaming;
                                // DB::enableQueryLog();
                                DB::table('stock_management_history')->insert([
                                    'user_id' => $user_id,
                                    'item_id' => $item_id,
                                    'item_price_id' => $price_size_id,
                                    'action_type' => $action_type,
                                    'open_stock' => $open_stock,
                                    'stock_value' => $stock_value,
                                    'closing_stock' => $closing_stock,
                                    'date_added' => $ordered_date,
                                    'reference_no' => $reference_no,
                                    'reference_key' => $reference_key,
                                    'shop_id' =>  $branch_id
                                ]);
                                // dd(DB::getQueryLog());
                            }
                        }
                    }
                }
            } //dd('123');
            return DB::table('sale_orders')->where('id', $sale_order_id)->first();
        } else {
            return false;
        }
    }

    public function get_sale_payment_types($sale_id)
    {
        return DB::table('sale_order_payments')->where('sale_order_id', $sale_id)->get();
    }

    public function get_credit_sale($sale_id)
    {
        return DB::table('credit_sale')->where('sale_order_id', $sale_id)->get();
    }

    public function StockManagementHistory($item_id, $id, $qty, $ref, $action)
    {
        //stock_management_history su
        $sql = "SELECT * FROM items WHERE id = '$item_id'";
        $item_details = mysqli_fetch_assoc(mysqli_query($GLOBALS['conn'], $sql));
        $item_id_i = $item_details['id'];
        $stock = $item_details['stock'];
        $stock_added = $stock + $qty;
        //stock_management_history add

        $user_id = auth()->user()->id;
        $item_id = $item_id_i;
        $reference_no = $id;
        $reference_key = $ref;
        $action_type = $action;
        $open_stock = $stock;
        $stock_value = $qty;
        $closing_stock = $stock_added;
        $ordered_date = date("Y-m-d H:i:s");

        mysqli_query($GLOBALS['conn'], "INSERT INTO stock_management_history (user_id, item_id, action_type, open_stock, stock_value, closing_stock, date_added, reference_no, reference_key,'shop_id') VALUES('$user_id', '$item_id', '$action_type', '$open_stock', '$stock_value', '$closing_stock', '$ordered_date', '$reference_no', '$reference_key')");
    }
}