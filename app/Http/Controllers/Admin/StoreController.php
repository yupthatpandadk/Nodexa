<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreCoupon;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Store\ProvisionStoreOrderService;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Nodexa\StripePaymentService;
use Pterodactyl\Services\Nodexa\GlobalStoreSaleService;
use Pterodactyl\Services\Nodexa\EggHostingPackageService;

class StoreController extends Controller
{
    public function index(StripePaymentService $stripe, GlobalStoreSaleService $sale){
        return view('admin.store.index',[
            'products'=>StoreProduct::orderBy('name')->get(),
            'orders'=>StoreOrder::with(['product','user','server'])->latest()->limit(100)->get(),
            'coupons'=>StoreCoupon::latest()->get(),
            'eggs'=>Egg::with('nest')->orderBy('name')->get(),
            'eggPackageProfiles'=>(new EggHostingPackageService())->allProfiles(),
            'eggPackageCategories'=>EggHostingPackageService::GAME_LABELS,
            'eggPackageTiers'=>EggHostingPackageService::TIERS,
            'eggPackageDetection'=>Egg::with('nest')->get()
                ->mapWithKeys(fn (Egg $egg) => [
                    $egg->id => (new EggHostingPackageService())->detectCategory($egg)
                ])->all(),
            'stripeSettings'=>$stripe->settingsForAdmin(),
            'globalSale'=>$sale->settings(),
        ]);
    }

    public function store(Request $r){
        $d=$r->validate(['name'=>'required|max:100','game'=>'required|max:80','description'=>'nullable|max:2000','price_monthly'=>'required|numeric|min:0','egg_id'=>'required|exists:eggs,id','memory'=>'required|integer|min:128','disk'=>'required|integer|min:256','cpu'=>'required|integer|min:10','databases'=>'required|integer|min:0','backups'=>'required|integer|min:0','allocations'=>'required|integer|min:1']);
        $d['slug']=Str::slug($d['name']).'-'.Str::lower(Str::random(5)); $d['enabled']=$r->boolean('enabled'); $d['featured']=$r->boolean('featured'); StoreProduct::create($d);
        return back()->with('success','Produkt oprettet.');
    }

    /**
     * Generate 1-4 draft Storefront hosting tiers for an installed Egg.
     * This action never modifies an existing product, order or service.
     */
    public function generateEggPackages(Request $request, EggHostingPackageService $packages)
    {
        $data = $request->validate([
            'egg_id' => 'required|integer|exists:eggs,id',
            'category' => 'required|in:auto,minecraft,fivem,rust,ark,voice,generic',
            'tiers' => 'required|array|min:1|max:4',
            'tiers.*' => 'required|in:starter,plus,pro,ultimate|distinct',
            'prices' => 'nullable|array',
            'prices.starter' => 'nullable|numeric|min:0.01|max:99999',
            'prices.plus' => 'nullable|numeric|min:0.01|max:99999',
            'prices.pro' => 'nullable|numeric|min:0.01|max:99999',
            'prices.ultimate' => 'nullable|numeric|min:0.01|max:99999',
        ]);
        $egg = Egg::with('nest')->findOrFail($data['egg_id']);

        try {
            $result = $packages->generate(
                $egg,
                $data['category'],
                $data['tiers'],
                $data['prices'] ?? []
            );
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['packages' => $exception->getMessage()]);
        }

        return back()->with('success', $result['created']
            . ' nye skjulte pakker oprettet til ' . $egg->name . '. '
            . $result['skipped'] . ' eksisterende pakker blev sprunget over. '
            . 'Kontrollér pris og ressourcer, før du aktiverer dem.');
    }

    /**
     * Edit Storefront product pricing/resources for FUTURE orders only.
     * Existing active Pterodactyl servers aren't resized by this action.
     */
    public function updateDetails(Request $r, StoreProduct $product)
    {
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'game' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'price_monthly' => 'required|numeric|min:0|max:99999',
            'memory' => 'required|integer|min:128|max:1048576',
            'disk' => 'required|integer|min:256|max:10485760',
            'cpu' => 'required|integer|min:10|max:6400',
            'databases' => 'required|integer|min:0|max:100',
            'backups' => 'required|integer|min:0|max:100',
            'allocations' => 'required|integer|min:1|max:100',
        ]);
        $product->update($d);

        return back()->with('success',
            'Pakken er opdateret til fremtidige bestillinger. '
            . 'Eksisterende servere og abonnementer er ikke ændret.');
    }

    public function update(Request $r, StoreProduct $product){
        $product->update(['enabled'=>$r->boolean('enabled'),'featured'=>$r->boolean('featured')]);
        return back()->with('success','Produkt opdateret.');
    }

    public function destroy(StoreProduct $product){ $product->delete(); return back()->with('success','Produkt slettet.'); }

    public function coupon(Request $r){
        $d=$r->validate(['code'=>'required|string|max:40|unique:nodexa_store_coupons,code','type'=>'required|in:percent,fixed','value'=>'required|numeric|min:0','duration_months'=>'required|integer|min:1|max:120','max_uses'=>'nullable|integer|min:1','expires_at'=>'nullable|date']);
        if ($d['type'] === 'percent' && (float) $d['value'] > 100) return back()->withErrors(['value'=>'Procentrabat kan højst være 100%.'])->withInput();
        $d['code']=strtoupper(trim($d['code'])); $d['enabled']=true; StoreCoupon::create($d);
        return back()->with('success','Rabatkode oprettet.');
    }

    public function globalSale(Request $r, GlobalStoreSaleService $sale){
        $d=$r->validate([
            'percent'=>'required|numeric|min:0|max:100',
            'duration_months'=>'required|integer|min:1|max:120',
            'label'=>'nullable|string|max:120',
        ]);

        $sale->save(
            $r->boolean('enabled'),
            (float) $d['percent'],
            (int) $d['duration_months'],
            $d['label'] ?? null
        );

        return back()->with('success',$r->boolean('enabled')
            ? 'Globalt udsalg er aktiveret.'
            : 'Globalt udsalg er deaktiveret.');
    }

    public function stripeSettings(Request $r, StripePaymentService $stripe){
        $d=$r->validate([
            'secret_key'=>'nullable|string|max:500',
            'webhook_secret'=>'nullable|string|max:500',
        ]);

        try {
            $stripe->saveSettings(
                $r->boolean('enabled'),
                $d['secret_key'] ?? null,
                $d['webhook_secret'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['stripe'=>$e->getMessage()]);
        }

        return back()->with('success','Stripe-indstillinger gemt.');
    }

    public function stripeTest(StripePaymentService $stripe){
        $result=$stripe->testConnection();

        return $result['success']
            ? back()->with('success',$result['message'])
            : back()->withErrors(['stripe'=>$result['message']]);
    }

    public function removeOrder(Request $request, StoreOrder $order, \Pterodactyl\Services\Nodexa\OrderRemovalService $removal, NodexaEventService $events)
    {
        try {
            $removal->removeLegacyOrder((int) $order->id, (int) $order->user_id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        $events->audit(
            (int) $request->user()->id,
            'billing',
            'legacy_order.cancelled',
            'Unpaid legacy order cancelled by admin',
            'store_order',
            (int) $order->id,
            [],
            $request
        );

        return back()->with('success', 'Den ubetalte ordre er annulleret og fjernet fra kundens oversigt.');
    }

    public function orderStatus(Request $r, StoreOrder $order, ProvisionStoreOrderService $provision, NodexaEventService $events){
        if ($order->hidden_from_client) {
            return back()->withErrors(['order' => 'Den annullerede ordre kan ikke genaktiveres. Opret en ny ordre i stedet.']);
        }

        $status=$r->validate(['status'=>'required|in:awaiting_payment,paid,active,cancelled,refunded'])['status'];
        if ($status === 'paid' && !$order->paid_at) $order->paid_at=now();
        if ($status === 'cancelled') $order->cancelled_at=now();
        $order->status=$status; $order->save();

        if ($status === 'paid' && !$order->server_id) {
            try { $provision->handle($order); $order->refresh(); }
            catch (\Throwable $e) { report($e); return back()->withErrors(['store'=>'Betaling markeret, men serveren kunne ikke provisioneres: '.$e->getMessage()]); }
        }

        if ($status === 'paid' && $order->server_id) {
            DB::table('nodexa_subscriptions')->updateOrInsert(
                ['order_id' => $order->id],
                [
                    'user_id' => $order->user_id,
                    'server_id' => $order->server_id,
                    'status' => 'active',
                    'amount' => $order->amount,
                    'currency' => $order->currency ?: 'DKK',
                    'interval' => 'monthly',
                    'next_invoice_at' => now()->addMonth(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $affiliateEvent = DB::table('nodexa_affiliate_events')
                ->where('order_id', $order->id)
                ->where('type', 'order_pending')
                ->first();

            if ($affiliateEvent) {
                $affiliate = DB::table('nodexa_affiliates')->where('id', $affiliateEvent->affiliate_id)->where('enabled', true)->first();
                if ($affiliate) {
                    $commission = round((float) $order->amount * ((float) $affiliate->commission_percent / 100), 2);

                    DB::table('nodexa_affiliate_events')->where('id', $affiliateEvent->id)->update([
                        'type' => 'conversion',
                        'amount' => $order->amount,
                        'commission' => $commission,
                        'updated_at' => now(),
                    ]);

                    DB::table('nodexa_affiliates')->where('id', $affiliate->id)->update([
                        'balance' => DB::raw('balance + ' . $commission),
                        'conversions' => DB::raw('conversions + 1'),
                        'updated_at' => now(),
                    ]);

                    $events->notify(
                        $affiliate->user_id,
                        'Ny affiliate-konvertering',
                        'Du har optjent ' . number_format($commission, 2, ',', '.') . ' DKK i provision.',
                        'billing',
                        '/client/hub'
                    );
                }
            }
        }

        $events->audit($r->user()->id, 'store', 'order.status', 'Order #' . $order->id . ' -> ' . $status, 'order', $order->id, ['status' => $status], $r);

        return back()->with('success','Ordrestatus opdateret.');
    }
}
