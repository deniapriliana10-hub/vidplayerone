<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$apiKey = trim((string)($config['api_key'] ?? ''));
$apiBase = rtrim((string)($config['api_base'] ?? ''), '/');

function apiGet(string $path, array $params = []): array {
    global $apiKey, $apiBase;

    if ($apiKey === '' || $apiKey === 'MASUKKAN_API_KEY_ADSTERRA_DISINI') {
        http_response_code(500);
        return ['error' => 'API Key Adsterra belum diisi di config.php'];
    }

    $url = $apiBase . '/' . ltrim($path, '/') . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-API-Key: ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $err) {
        http_response_code(502);
        return ['error' => 'Gagal menghubungi Adsterra: ' . $err];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        http_response_code(502);
        return ['error' => 'Response Adsterra bukan JSON yang valid.', 'raw' => substr($body, 0, 500)];
    }

    if ($code >= 400) {
        http_response_code($code);
        return ['error' => $json['message'] ?? $json['error'] ?? 'Adsterra mengembalikan HTTP ' . $code, 'response' => $json];
    }

    return $json;
}

$action = $_GET['action'] ?? '';
if ($action !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($action === 'stats') {
        $start = $_GET['start_date'] ?? '';
        $finish = $_GET['finish_date'] ?? '';
        $domain = $_GET['domain'] ?? '';
        $placement = $_GET['placement'] ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $finish)) {
            http_response_code(400);
            echo json_encode(['error' => 'Tanggal tidak valid.']);
            exit;
        }

        $params = [
            'start_date' => $start,
            'finish_date' => $finish,
            'group_by[]' => 'placement_sub_id'
        ];

        // Opsional: isi filter domain/placement dari UI.
        if ($domain !== '') $params['domain'] = $domain;
        if ($placement !== '') $params['placement'] = $placement;

        echo json_encode(apiGet('stats.json', $params), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'domains') {
        echo json_encode(apiGet('domains.json'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'placements') {
        $domain = $_GET['domain'] ?? '';
        $params = [];
        if ($domain !== '') $params['domain'] = $domain;
        echo json_encode(apiGet('placements.json', $params), JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Action tidak dikenal']);
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adsterra Direct Statistics</title>
<style>
:root{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f6f8;color:#17202a}
*{box-sizing:border-box} body{margin:0}.wrap{max-width:1100px;margin:auto;padding:20px}
header{background:#111827;color:#fff;border-radius:18px;padding:22px;margin-bottom:16px}
h1{margin:0 0 6px;font-size:24px} .muted{opacity:.75;font-size:13px}
.panel,.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;box-shadow:0 4px 18px rgba(0,0,0,.04)}
.filters{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
label{display:block;font-size:12px;font-weight:700;margin-bottom:6px}
input,select,button{width:100%;height:42px;border:1px solid #d1d5db;border-radius:10px;padding:0 11px;font-size:14px;background:#fff}
button{background:#111827;color:#fff;border:0;font-weight:700;cursor:pointer;padding:0 18px}
button:disabled{opacity:.5}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}
.metric small{display:block;color:#6b7280}.metric strong{display:block;font-size:23px;margin-top:5px}
.tablebox{overflow:auto;margin-top:16px}.status{margin-top:10px;font-size:13px;color:#6b7280}
table{width:100%;border-collapse:collapse;min-width:700px}th,td{padding:12px 10px;border-bottom:1px solid #eee;text-align:left;font-size:14px}th{font-size:12px;color:#6b7280;text-transform:uppercase}
.money{font-weight:800}.error{color:#b91c1c}.ok{color:#047857}
@media(max-width:800px){.filters{grid-template-columns:1fr 1fr}.filters .wide{grid-column:1/-1}.summary{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="wrap">
<header>
  <h1>Adsterra Direct Statistics</h1>
  <div class="muted">Penghasilan Smartlink/Direct berdasarkan placement_sub_id</div>
</header>

<div class="panel">
  <div class="filters">
    <div><label>Dari tanggal</label><input id="start" type="date"></div>
    <div><label>Sampai tanggal</label><input id="finish" type="date"></div>
    <div><label>Domain (opsional)</label><select id="domain"><option value="">Semua domain</option></select></div>
    <div><label>Placement (opsional)</label><select id="placement"><option value="">Semua placement</option></select></div>
    <div><button id="load">CEK STATISTIK</button></div>
  </div>
  <div id="status" class="status"></div>
</div>

<div class="summary">
  <div class="card metric"><small>Total Revenue</small><strong id="revenue">$0.00</strong></div>
  <div class="card metric"><small>Impressions</small><strong id="impressions">0</strong></div>
  <div class="card metric"><small>Clicks</small><strong id="clicks">0</strong></div>
  <div class="card metric"><small>CTR</small><strong id="ctr">0%</strong></div>
</div>

<div class="card tablebox">
<table>
<thead><tr><th>Direct / Sub ID</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>CPM</th><th>Revenue</th></tr></thead>
<tbody id="rows"><tr><td colspan="6">Pilih tanggal lalu klik Cek Statistik.</td></tr></tbody>
</table>
</div>
</div>

<script>
const $ = id => document.getElementById(id);
const fmt = n => Number(n||0).toLocaleString('id-ID');
const money = n => '$' + Number(n||0).toFixed(4);
const today = new Date();
const iso = d => d.toISOString().slice(0,10);
$('finish').value = iso(today);
const prev = new Date(today); prev.setDate(prev.getDate()-6);
$('start').value = iso(prev);

function first(obj, keys, fallback=0){
  for(const k of keys) if(obj && obj[k] !== undefined && obj[k] !== null) return obj[k];
  return fallback;
}
function rowsFrom(data){
  if(Array.isArray(data)) return data;
  for(const k of ['data','results','stats','items']) if(Array.isArray(data?.[k])) return data[k];
  return [];
}
function nameOf(r){
  return first(r,['placement_sub_id','placementSubId','sub_id','subid','name','placement'],'Unknown');
}
function num(r, keys){ return Number(first(r,keys,0)) || 0; }

async function loadFilters(){
  try{
    const d = await fetch('?action=domains').then(r=>r.json());
    const arr = rowsFrom(d);
    arr.forEach(x=>{
      const id = first(x,['id','domain_id']);
      const name = first(x,['name','domain','url'],id);
      if(id!==0 && id!==undefined){
        const o=document.createElement('option');o.value=id;o.textContent=name+' ('+id+')';$('domain').appendChild(o);
      }
    });
  }catch(e){}
}
$('domain').addEventListener('change', async ()=>{
  $('placement').innerHTML='<option value="">Semua placement</option>';
  if(!$('domain').value)return;
  try{
    const d=await fetch('?action=placements&domain='+encodeURIComponent($('domain').value)).then(r=>r.json());
    rowsFrom(d).forEach(x=>{
      const id=first(x,['id','placement_id']); const name=first(x,['name','placement','title'],id);
      if(id!==undefined){const o=document.createElement('option');o.value=id;o.textContent=name+' ('+id+')';$('placement').appendChild(o);}
    });
  }catch(e){}
});

$('load').onclick = async ()=>{
  const start=$('start').value, finish=$('finish').value;
  if(!start||!finish){$('status').textContent='Pilih tanggal terlebih dahulu.';return}
  if(start>finish){$('status').textContent='Tanggal awal tidak boleh lebih besar dari tanggal akhir.';return}
  $('load').disabled=true;$('status').textContent='Mengambil data dari Adsterra...';
  try{
    const q=new URLSearchParams({action:'stats',start_date:start,finish_date:finish});
    if($('domain').value)q.set('domain',$('domain').value);
    if($('placement').value)q.set('placement',$('placement').value);
    const res=await fetch('?'+q.toString());
    const data=await res.json();
    if(!res.ok || data.error) throw new Error(data.error||'Request gagal');
    const arr=rowsFrom(data);
    let total={imp:0,click:0,rev:0};
    $('rows').innerHTML='';
    if(!arr.length){
      $('rows').innerHTML='<tr><td colspan="6">Tidak ada data untuk periode tersebut.</td></tr>';
    }
    arr.forEach(r=>{
      const imp=num(r,['impressions','impression']);
      const click=num(r,['clicks','click']);
      const rev=num(r,['revenue','earning','earnings']);
      const ctr=imp?click/imp*100:num(r,['ctr']);
      const cpm=imp?rev/imp*1000:num(r,['cpm']);
      total.imp+=imp; total.click+=click; total.rev+=rev;
      const tr=document.createElement('tr');
      tr.innerHTML='<td><b>'+String(nameOf(r)).replace(/[<>&"]/g,'')+'</b></td>'+
        '<td>'+fmt(imp)+'</td><td>'+fmt(click)+'</td><td>'+ctr.toFixed(2)+'%</td>'+
        '<td>$'+cpm.toFixed(4)+'</td><td class="money">'+money(rev)+'</td>';
      $('rows').appendChild(tr);
    });
    $('revenue').textContent=money(total.rev);
    $('impressions').textContent=fmt(total.imp);
    $('clicks').textContent=fmt(total.click);
    $('ctr').textContent=(total.imp?total.click/total.imp*100:0).toFixed(2)+'%';
    $('status').innerHTML='<span class="ok">Berhasil: '+start+' sampai '+finish+'</span>';
  }catch(e){
    $('status').innerHTML='<span class="error">'+e.message+'</span>';
    $('rows').innerHTML='<tr><td colspan="6">Gagal mengambil data.</td></tr>';
  }finally{$('load').disabled=false}
};
loadFilters();
</script>
</body>
</html>
