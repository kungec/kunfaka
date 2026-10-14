<?php
/**
 * 优惠券营销插件
 * 表结构(coupons/coupon_used)随核心迁移(Upgrade)统一创建, 本类只做读写
 * renderInput():    购买页优惠码输入框HTML
 * applyCoupon():    校验码并计算抵扣, 返回 ['ok'=>bool,'discount'=>金额,'code'=>码,'msg'=>提示]
 * onPaid():         支付成功核销(次数+1, 记录使用明细)
 * renderAdmin():    后台管理页HTML(列表+新建)
 * handleAdminPost():处理后台管理POST, 返回提示消息
 */
class CouponsPlugin
{
    public $code = 'coupons';
    public $name = '优惠券';
    public $enabled = false;
    public $config = [];

    /** 插件初始化钩子(数据表由核心迁移管理, 此处无需建表) */
    public function install()
    {
    }

    /** 购买页优惠码输入框 */
    public function renderInput(array $ctx)
    {
        $code = e(trim((string)(isset($_POST['coupon']) ? $_POST['coupon'] : '')));
        return '<div style="margin:0 0 12px">'
            . '<label style="display:block;font-size:13px;color:var(--muted,#6e6e73);margin-bottom:6px">🎁 优惠码(有码必填, 无码留空)</label>'
            . '<input type="text" name="coupon" value="' . $code . '" maxlength="50" placeholder="输入优惠码, 结账时自动抵扣" '
            . 'style="width:100%;box-sizing:border-box;padding:11px 15px;border:1.5px solid var(--border,rgba(0,0,0,.1));border-radius:10px;font-size:14px;background:rgba(255,255,255,.92);color:var(--text,#1d1d1f)">'
            . '</div>';
    }

    /** 校验并计算抵扣; 未填码静默放行 */
    public function applyCoupon($code, array $ctx)
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') return ['ok' => false, 'discount' => 0, 'code' => '', 'msg' => ''];
        $total = (float)$ctx['total'];
        $fail = function ($msg) { return ['ok' => false, 'discount' => 0, 'code' => '', 'msg' => $msg]; };
        $c = DB::fetch('SELECT * FROM coupons WHERE code = ?', [$code]);
        if (!$c || (int)$c['status'] !== 1) return $fail('优惠码不存在或已停用');
        $t = now();
        if ((int)$c['starts'] > 0 && $t < (int)$c['starts']) return $fail('优惠码尚未到生效时间');
        if ((int)$c['ends'] > 0 && $t > (int)$c['ends']) return $fail('优惠码已过期');
        if ((int)$c['max_uses'] > 0) {
            // 占用计算法: 已核销(used) + 在途占用(待支付/待处理订单) 均计入名额; 订单过期(状态2)自动释放
            $pending = (int)DB::value('SELECT COUNT(*) FROM orders WHERE coupon_code = ? AND status IN (0, 3)', [$c['code']]);
            if ((int)$c['used'] + $pending >= (int)$c['max_uses']) return $fail('优惠码已被领完');
        }
        $pids = array_filter(array_map('intval', explode(',', (string)$c['pids'])));
        if ($pids && !in_array((int)$ctx['product_id'], $pids, true)) return $fail('该优惠码不适用于此商品');
        if ((float)$c['min_order'] > 0 && $total < (float)$c['min_order']) return $fail('该优惠码需满 ¥' . nf($c['min_order']) . ' 可用');
        // 联系方式统一小写比较, 防大小写变体绕过每联系方式限用
        $contact = strtolower(trim((string)$ctx['contact']));
        $usedByMe = (int)DB::value('SELECT COUNT(*) FROM coupon_used WHERE coupon_id = ? AND contact = ?', [$c['id'], $contact]);
        if ((int)$c['per_user'] > 0 && $usedByMe >= (int)$c['per_user']) return $fail('该优惠码每联系方式限用 ' . (int)$c['per_user'] . ' 次, 您已使用');
        // 注意: 此处是字符串比较, 不能写成 (float)$c['type'] === 'percent'(恒为假, 百分比券会按固定金额扣)
        $discount = (string)$c['type'] === 'percent'
            ? round($total * min(100, max(0, (float)$c['value'])) / 100, 2)
            : min((float)$c['value'], $total);
        $discount = min($discount, round($total - 0.01, 2));
        if ($discount <= 0) return $fail('优惠码抵扣无效');
        return ['ok' => true, 'discount' => $discount, 'code' => $code, 'title' => $c['title'], 'msg' => ''];
    }

    /** 支付成功核销 */
    public function onPaid(array $order)
    {
        if (empty($order['coupon_code'])) return;
        $c = DB::fetch('SELECT id FROM coupons WHERE code = ?', [$order['coupon_code']]);
        if (!$c) return;
        DB::exec('UPDATE coupons SET used = used + 1 WHERE id = ?', [$c['id']]);
        DB::insert('coupon_used', [
            'coupon_id' => $c['id'],
            'order_sn' => $order['sn'],
            'contact' => strtolower(trim((string)$order['contact'])),
            'amount' => $order['discount'],
            'created_at' => now(),
        ]);
    }

    /* ---------- 后台管理(经 应用商店→优惠券→管理 进入) ---------- */

    public function renderAdmin($msg = '')
    {
        $rows = DB::fetchAll('SELECT * FROM coupons ORDER BY id DESC LIMIT 200');
        $h = '<div class="card"><h3>🎁 优惠券管理<span class="tag pro">专业版插件</span></h3>';
        if ($msg !== '') $h .= '<div class="desc" style="color:var(--ok,#4ade80);font-weight:600">' . e($msg) . '</div>';
        $h .= '<form method="post" action="' . au('plugin_admin', ['name' => 'coupons']) . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px 12px;margin:12px 0">'
            . '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="act" value="create">'
            . '<div><label>优惠码 *(大写字母数字)</label><input name="code" maxlength="50" placeholder="留空自动生成" style="width:100%"></div>'
            . '<div><label>名称备注</label><input name="title" maxlength="100" placeholder="如 双十一促销" style="width:100%"></div>'
            . '<div><label>类型</label><select name="type" style="width:100%"><option value="fixed">固定金额(元)</option><option value="percent">百分比(减%)</option></select></div>'
            . '<div><label>面额/百分比 *</label><input type="number" name="value" step="0.01" min="0.01" value="5" style="width:100%"></div>'
            . '<div><label>最低消费(元, 0不限)</label><input type="number" name="min_order" step="0.01" min="0" value="0" style="width:100%"></div>'
            . '<div><label>总限用次数(0不限)</label><input type="number" name="max_uses" min="0" value="100" style="width:100%"></div>'
            . '<div><label>每联系方式限用</label><input type="number" name="per_user" min="0" value="1" style="width:100%"></div>'
            . '<div><label>限定商品ID(逗号分隔, 空=全部)</label><input name="pids" maxlength="500" placeholder="如 1,3,5" style="width:100%"></div>'
            . '<div><label>失效时间(留空=永久)</label><input type="datetime-local" name="ends_str" style="width:100%"></div>'
            . '<div style="display:flex;align-items:flex-end"><button class="btn" type="submit">＋ 创建优惠券</button></div>'
            . '</form></div>';
        $h .= '<div class="card"><table class="tb"><thead><tr><th>优惠码</th><th>名称</th><th>面额</th><th>已用/限用</th><th>状态</th><th>有效期</th><th>操作</th></tr></thead><tbody>';
        if (!$rows) $h .= '<tr><td colspan="7" style="text-align:center;color:var(--muted)">暂无优惠券, 先在上方创建一个</td></tr>';
        foreach ($rows as $r) {
            $face = $r['type'] === 'percent' ? '减' . nf($r['value']) . '%' : '¥' . nf($r['value']);
            $limit = (int)$r['max_uses'] > 0 ? (int)$r['used'] . '/' . (int)$r['max_uses'] : (int)$r['used'] . '/∞';
            $range = (int)$r['ends'] > 0 ? '至 ' . date('Y-m-d H:i', $r['ends']) : '长期';
            $h .= '<tr><td class="mono"><b>' . e($r['code']) . '</b></td><td>' . e($r['title'] ?: '—') . '</td><td>' . e($face)
                . '</td><td>' . e($limit) . '</td><td><span class="tag ' . ((int)$r['status'] === 1 ? 'ok' : 'bad') . '">' . ((int)$r['status'] === 1 ? '启用' : '停用') . '</span></td>'
                . '<td class="dim">' . e($range) . '</td><td><button class="btn sm gray" data-cact="' . ((int)$r['status'] === 1 ? 'disable' : 'enable') . '" data-cid="' . (int)$r['id'] . '">' . ((int)$r['status'] === 1 ? '停用' : '启用') . '</button> '
                . '<button class="btn sm red" data-cact="del" data-cid="' . (int)$r['id'] . '" data-confirm="确定删除该优惠券?">删除</button></td></tr>';
        }
        $h .= '</tbody></table></div>';
        $h .= '<script>document.addEventListener("click",function(ev){var t=ev.target.closest?ev.target.closest("[data-cact]"):null;if(!t)return;'
            . 'if(t.hasAttribute("data-confirm")&&!confirm(t.getAttribute("data-confirm")))return;'
            . 'var fd=new FormData();fd.append("_csrf","' . e(csrf_token()) . '");fd.append("act",t.getAttribute("data-cact"));fd.append("id",t.getAttribute("data-cid"));'
            . 'fetch("' . au('plugin_admin', ['name' => 'coupons']) . '",{method:"POST",body:fd,headers:{"X-Requested-With":"XMLHttpRequest"}}).then(function(r){return r.json()}).then(function(d){kAlert(d.msg||d);if(d.code===0)location.reload()});});</script>';
        return $h;
    }

    public function handleAdminPost()
    {
        $act = trim((string)arr_get($_POST, 'act'));
        if ($act === 'create') {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)arr_get($_POST, 'code')));
            if ($code === '') {
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            if (strlen($code) < 3) return '优惠码至少3位';
            if (DB::value('SELECT id FROM coupons WHERE code = ?', [$code])) return '优惠码已存在';
            $value = round((float)arr_get($_POST, 'value', 0), 2);
            if ($value <= 0) return '面额必须大于0';
            $type = arr_get($_POST, 'type') === 'percent' ? 'percent' : 'fixed';
            if ($type === 'percent' && $value > 100) return '百分比不能超过100';
            $endsStr = trim((string)arr_get($_POST, 'ends_str'));
            $ends = $endsStr !== '' && ($ts = strtotime($endsStr)) > 0 ? $ts : 0;
            DB::insert('coupons', [
                'code' => $code,
                'title' => mb_substr(trim((string)arr_get($_POST, 'title')), 0, 100),
                'type' => $type,
                'value' => $value,
                'min_order' => max(0, round((float)arr_get($_POST, 'min_order', 0), 2)),
                'max_uses' => max(0, (int)arr_get($_POST, 'max_uses', 0)),
                'per_user' => max(0, (int)arr_get($_POST, 'per_user', 1)),
                'pids' => preg_replace('/[^0-9,]/', '', (string)arr_get($_POST, 'pids')),
                'ends' => $ends,
                'created_at' => now(),
            ]);
            return '优惠券 ' . $code . ' 创建成功';
        }
        if (in_array($act, ['enable', 'disable', 'del'], true)) {
            $id = (int)arr_get($_POST, 'id');
            if (!$id) return '参数错误';
            if ($act === 'del') {
                DB::exec('DELETE FROM coupons WHERE id = ?', [$id]);
                return '已删除';
            }
            DB::update('coupons', ['status' => $act === 'enable' ? 1 : 0], 'id = ?', [$id]);
            return $act === 'enable' ? '已启用' : '已停用';
        }
        return '';
    }
}
