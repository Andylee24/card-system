<?php
namespace App\Http\Controllers\Admin; use App\Library\FundHelper; use App\Library\Helper; use Carbon\Carbon; use Illuminate\Database\Eloquent\Relations\Relation; use Illuminate\Http\Request; use App\Http\Controllers\Controller; use App\Library\Response; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Log; class Order extends Controller { public function delete(Request $sp147552) { $this->validate($sp147552, array('ids' => 'required|string', 'income' => 'required|integer', 'balance' => 'required|integer')); $sp548f2b = $sp147552->post('ids'); $spac2a92 = (int) $sp147552->post('income'); $spa50328 = (int) $sp147552->post('balance'); \App\Order::whereIn('id', explode(',', $sp548f2b))->chunk(100, function ($spbb53f2) use($spac2a92, $spa50328) { foreach ($spbb53f2 as $sp7c328e) { $sp7c328e->cards()->detach(); try { if ($spac2a92) { $sp7c328e->fundRecord()->delete(); } if ($spa50328) { $spe2c9ac = \App\User::lockForUpdate()->firstOrFail(); $spe2c9ac->m_all -= $sp7c328e->income; $spe2c9ac->saveOrFail(); } $sp7c328e->delete(); } catch (\Exception $spbd4f27) { } } }); return Response::success(); } function freeze(Request $sp147552) { $this->validate($sp147552, array('ids' => 'required|string')); $sp548f2b = explode(',', $sp147552->post('ids')); $sp0bd758 = $sp147552->post('reason'); $sp75f1cf = 0; $spa48ffd = 0; foreach ($sp548f2b as $spb6ceba) { $sp75f1cf++; if (FundHelper::orderFreeze($spb6ceba, $sp0bd758)) { $spa48ffd++; } } return Response::success(array($sp75f1cf, $spa48ffd)); } function unfreeze(Request $sp147552) { $this->validate($sp147552, array('ids' => 'required|string')); $sp548f2b = explode(',', $sp147552->post('ids')); $sp75f1cf = 0; $spa48ffd = 0; $spd18e4e = \App\Order::STATUS_FROZEN; foreach ($sp548f2b as $spb6ceba) { $sp75f1cf++; if (FundHelper::orderUnfreeze($spb6ceba, '后台操作', null, $spd18e4e)) { $spa48ffd++; } } return Response::success(array($sp75f1cf, $spa48ffd, $spd18e4e)); } function set_paid(Request $sp147552) { $this->validate($sp147552, array('id' => 'required|integer')); $speb3ceb = $sp147552->post('id', ''); $sp7926dc = $sp147552->post('trade_no', ''); if (strlen($sp7926dc) < 1) { return Response::forbidden('请输入支付系统内单号'); } $sp7c328e = \App\Order::findOrFail($speb3ceb); if ($sp7c328e->status !== \App\Order::STATUS_UNPAY) { return Response::forbidden('只能操作未支付订单'); } $spf63a25 = 'Admin.SetPaid'; $sp3bc683 = $sp7c328e->order_no; $sp6d2f0f = $sp7c328e->paid; try { Log::debug($spf63a25 . " shipOrder start, order_no: {$sp3bc683}, amount: {$sp6d2f0f}, trade_no: {$sp7926dc}"); (new \App\Http\Controllers\Shop\Pay())->shipOrder($sp147552, $sp3bc683, $sp6d2f0f, $sp7926dc); Log::debug($spf63a25 . ' shipOrder end, order_no: ' . $sp3bc683); $spa48ffd = true; $sp7ee4b9 = '发货成功'; } catch (\Exception $spbd4f27) { $spa48ffd = false; $sp7ee4b9 = $spbd4f27->getMessage(); Log::error($spf63a25 . ' shipOrder Exception: ' . $spbd4f27->getMessage()); } $sp7c328e = \App\Order::with(array('pay' => function (Relation $spd10097) { $spd10097->select(array('id', 'name')); }, 'card_orders.card' => function (Relation $spd10097) { $spd10097->select(array('id', 'card')); }))->findOrFail($speb3ceb); if ($sp7c328e->status === \App\Order::STATUS_PAID) { if ($sp7c328e->product->delivery === \App\Product::DELIVERY_MANUAL) { $spa48ffd = true; $sp7ee4b9 = '已标记为付款成功<br>当前商品为手动发货商品, 请手动进行发货。'; } else { $spa48ffd = false; $sp7ee4b9 = '已标记为付款成功, <br>但是买家库存不足, 发货失败, 请稍后尝试手动发货。'; } } return Response::success(array('code' => $spa48ffd ? 0 : -1, 'msg' => $sp7ee4b9, 'order' => $sp7c328e)); } }