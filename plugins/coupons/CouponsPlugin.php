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

    /** 购买页优惠码输入框(极简一行式: 内嵌图标+主题输入框同视觉语言, 变量级联适配全部主题亮/暗模式) */
    public function renderInput(array $ctx)
    {
        $code = e(trim((string)(isset($_POST['coupon']) ? $_POST['coupon'] : '')));
        $css = '.cpn-wrap{margin:2px 0 14px}
.cpn-field{--cpn-accent:var(--accent,var(--primary,#4f46e5));--cpn-ring:var(--accent-ring,var(--primary-soft,rgba(79,70,229,.2)));--cpn-line:var(--border,var(--line,rgba(0,0,0,.12)));display:flex;align-items:center;gap:10px;height:46px;padding:0 14px;background:var(--card-2,var(--card,#fff));border:1.5px solid var(--cpn-line);border-radius:11px;transition:border-color .18s ease,box-shadow .18s ease}
.cpn-field:focus-within{border-color:var(--cpn-accent);box-shadow:0 0 0 3px var(--cpn-ring);background:var(--card-solid,var(--card,#fff))}
.cpn-field .cpn-ic{display:flex;color:var(--muted,#6e6e73);flex:none;transition:color .18s ease}
.cpn-field:focus-within .cpn-ic{color:var(--cpn-accent)}
.cpn-field input{flex:1;min-width:0;border:0;outline:0;background:transparent;padding:0;font-size:14px;font-family:inherit;color:var(--text,#1d1d1f);box-shadow:none}
.cpn-field input::placeholder{color:var(--muted,#6e6e73)}';
        return '<div class="cpn-wrap"><style>' . $css . '</style>'
            . '<div class="cpn-field">'
            . '<span class="cpn-ic">' . ico('ticket', 17) . '</span>'
            . '<input type="text" name="coupon" value="' . $code . '" maxlength="50" placeholder="输入优惠码(选填), 结账时自动抵扣" aria-label="优惠码" autocomplete="off">'
            . '</div></div>';
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
        $pids = array_filter(array_map('intval', explode(',', (string)$c['pids'])));
        if ($pids && !in_array((int)$ctx['product_id'], $pids, true)) return $fail('该优惠码不适用于此商品');
        if ((float)$c['min_order'] > 0 && $total < (float)$c['min_order']) return $fail('该优惠码需满 ¥' . nf($c['min_order']) . ' 可用');
        if ((int)$c['max_uses'] > 0) {
            // 占用计算法: 已核销(used) + 在途占用(待支付/待处理订单) 均计入名额; 订单过期(状态2)自动释放
            $pending = (int)DB::value('SELECT COUNT(*) FROM orders WHERE coupon_code = ? AND status IN (0, 3)', [$c['code']]);
            if ((int)$c['used'] + $pending >= (int)$c['max_uses']) return $fail('优惠码已被领完');
        }
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
        $stats = DB::fetch('SELECT COUNT(*) AS total, COALESCE(SUM(status = 1), 0) AS active, COALESCE(SUM(used), 0) AS redeemed FROM coupons');
        $given = (float)DB::value('SELECT COALESCE(SUM(amount), 0) FROM coupon_used');
        $css = '.cpn-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:14px 0}
.cpn-kpi{background:var(--card2,#1e2023);border:1px solid var(--border,#26282b);border-radius:10px;padding:12px 14px}
.cpn-kpi b{display:block;font-size:20px;line-height:1.3;color:var(--text,#e8e8e8)}
.cpn-kpi span{font-size:12px;color:var(--muted,#71757b)}
.cpn-sec{font-size:12px;font-weight:600;color:var(--muted,#71757b);letter-spacing:.05em;margin:16px 0 2px;display:flex;align-items:center;gap:6px}
.cpn-sec::after{content:"";flex:1;height:1px;background:var(--border,#26282b)}
.cpn-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:12px;margin-top:12px}
.cpn-card{display:flex;align-items:stretch;background:var(--card,#18191b);border:1px solid var(--border,#26282b);border-radius:12px;overflow:hidden;min-width:0}
.cpn-face{flex:none;width:118px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:16px 8px;background:linear-gradient(150deg,#6366f1,#8b5cf6);color:#fff;text-align:center}
.cpn-face b{font-size:21px;line-height:1.2}
.cpn-face i{font-style:normal;font-size:11px;opacity:.85;white-space:nowrap}
.cpn-body{flex:1;min-width:0;padding:11px 13px;display:flex;flex-direction:column;gap:7px}
.cpn-row1{display:flex;align-items:center;gap:8px}
.cpn-row1 b{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cpn-code{display:flex;align-items:center;gap:8px;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:13px;color:var(--text2,#b0b3b8)}
.cpn-code button{border:1px solid var(--border,#26282b);background:transparent;color:var(--muted,#71757b);border-radius:6px;padding:1px 8px;font-size:11px;cursor:pointer}
.cpn-code button:hover{color:var(--text,#e8e8e8);border-color:var(--muted,#71757b)}
.cpn-prog-row{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted,#71757b)}
.cpn-prog{flex:1;height:5px;background:var(--border,#26282b);border-radius:3px;overflow:hidden}
.cpn-prog i{display:block;height:100%;border-radius:3px;background:linear-gradient(90deg,#6366f1,#a855f7)}
.cpn-foot{display:flex;align-items:center;gap:6px;margin-top:2px}
.cpn-foot .dim{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cpn-empty{padding:34px 0;text-align:center;color:var(--muted,#71757b)}
@media (max-width:560px){.cpn-cards{grid-template-columns:1fr}.cpn-face{width:96px}.cpn-face b{font-size:18px}}';
        $h = '<style>' . $css . '</style>';
        $h .= '<div class="card"><h3>' . ico('ticket', 18) . ' 优惠券管理<span class="tag pro">专业版插件</span></h3>';
        if ($msg !== '') $h .= '<div class="desc" style="color:var(--ok,#4ade80);font-weight:600">' . e($msg) . '</div>';
        $h .= '<div class="cpn-kpis">'
            . '<div class="cpn-kpi"><b>' . (int)$stats['total'] . '</b><span>券种总数</span></div>'
            . '<div class="cpn-kpi"><b>' . (int)$stats['active'] . '</b><span>启用中</span></div>'
            . '<div class="cpn-kpi"><b>' . (int)$stats['redeemed'] . '</b><span>累计核销</span></div>'
            . '<div class="cpn-kpi"><b>¥' . nf($given) . '</b><span>累计让利</span></div>'
            . '</div>';
        $h .= '<div class="cpn-sec">创建优惠券</div>';
        $h .= '<form method="post" action="' . au('plugin_admin', ['name' => 'coupons']) . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px 12px;margin:8px 0 4px">'
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
            . '<div style="display:flex;align-items:flex-end"><button class="btn" type="submit">' . ico('plus', 14) . ' 创建优惠券</button></div>'
            . '</form></div>';
        $h .= '<div class="card"><div class="cpn-sec" style="margin-top:0">已发放的优惠券</div>';
        if (!$rows) {
            $h .= '<div class="cpn-empty">' . ico('ticket', 26) . '<br>暂无优惠券, 在上方创建第一张</div>';
        } else {
            $h .= '<div class="cpn-cards">';
            $t = now();
            foreach ($rows as $r) {
                $expired = (int)$r['ends'] > 0 && (int)$r['ends'] < $t;
                if ($expired) {
                    $tag = '<span class="tag bad">已过期</span>';
                } elseif ((int)$r['status'] === 1) {
                    $tag = '<span class="tag ok">启用中</span>';
                } else {
                    $tag = '<span class="tag" style="background:var(--border,#26282b);color:var(--muted,#71757b)">停用</span>';
                }
                $faceVal = $r['type'] === 'percent' ? e(nf($r['value'])) . '<small style="font-size:12px">%</small>' : '¥' . e(nf($r['value']));
                $faceCond = (float)$r['min_order'] > 0 ? '满¥' . nf($r['min_order']) . '可用' : '无门槛';
                $maxUses = (int)$r['max_uses'];
                $used = (int)$r['used'];
                if ($maxUses > 0) {
                    $pct = min(100, round($used / $maxUses * 100));
                    $prog = '<div class="cpn-prog"><i style="width:' . $pct . '%"></i></div><span>' . $used . '/' . $maxUses . '</span>';
                } else {
                    $prog = '<div class="cpn-prog"><i style="width:' . min(100, $used * 5) . '%;opacity:.45"></i></div><span>' . $used . '/∞</span>';
                }
                $range = (int)$r['ends'] > 0 ? '有效期至 ' . date('Y-m-d H:i', $r['ends']) : '长期有效';
                $h .= '<div class="cpn-card">'
                    . '<div class="cpn-face"><b>' . $faceVal . '</b><i>' . e($faceCond) . '</i></div>'
                    . '<div class="cpn-body">'
                    . '<div class="cpn-row1"><b title="' . e($r['title']) . '">' . e($r['title'] ?: '未命名优惠券') . '</b>' . $tag . '</div>'
                    . '<div class="cpn-code"><span>' . e($r['code']) . '</span><button type="button" data-ccopy="' . e($r['code']) . '">复制</button></div>'
                    . '<div class="cpn-prog-row">' . $prog . '</div>'
                    . '<div class="cpn-foot"><span class="dim">' . e($range) . '</span>'
                    . '<button class="btn sm gray" data-cact="' . ((int)$r['status'] === 1 ? 'disable' : 'enable') . '" data-cid="' . (int)$r['id'] . '">' . ((int)$r['status'] === 1 ? '停用' : '启用') . '</button> '
                    . '<button class="btn sm red" data-cact="del" data-cid="' . (int)$r['id'] . '" data-confirm="确定删除该优惠券?">删除</button></div>'
                    . '</div></div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
        $h .= '<script>document.addEventListener("click",function(ev){var c=ev.target.closest?ev.target.closest("[data-ccopy]"):null;if(c&&navigator.clipboard){navigator.clipboard.writeText(c.getAttribute("data-ccopy"));kAlert("已复制 "+c.getAttribute("data-ccopy"));return;}'
            . 'var t=ev.target.closest?ev.target.closest("[data-cact]"):null;if(!t)return;'
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
