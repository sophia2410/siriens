<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/modules/common/database.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function vd($v){$d=DateTime::createFromFormat('Y-m-d',$v);return $d&&$d->format('Y-m-d')===$v;}
function rows($db,$q,$t='',$p=[]){$s=$db->prepare($q);if($t)$s->bind_param($t,...$p);$s->execute();$r=$s->get_result();$a=[];while($x=$r->fetch_assoc())$a[]=$x;$s->close();return $a;}
function back($d){header('Location: market_flow.php?date='.urlencode($d).'&saved=1');exit;}
function dc($d){return $d==='호재'?'good':($d==='악재'?'bad':'neutral');}
$date=$_GET['date']??date('Y-m-d');if(!vd($date))$date=date('Y-m-d');
$themeFilter=(int)($_GET['theme_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
 $a=$_POST['action']??'';$rd=$_POST['return_date']??$date;if(!vd($rd))$rd=date('Y-m-d');
 if($a==='theme'){
  $x=trim($_POST['title']??'');if($x!==''){$s=$mysqli->prepare("INSERT IGNORE INTO market_theme(title) VALUES(?)");$s->bind_param('s',$x);$s->execute();$s->close();}back($rd);
 }
 if($a==='theme_status'){
  $id=(int)$_POST['theme_id'];$st=$_POST['status']==='종료'?'종료':'진행중';
  $s=$mysqli->prepare("UPDATE market_theme SET status=? WHERE id=?");$s->bind_param('si',$st,$id);$s->execute();$s->close();back($rd);
 }
 if($a==='event'){
  $ed=$_POST['event_date']??'';$ti=trim($_POST['title']??'');$mv=trim($_POST['market_view']??'');
  $di=$_POST['direction']??'중립';$me=trim($_POST['memo']??'');$cid=(int)($_POST['calendar_id']??0);$cv=$cid?:null;
  $ids=array_unique(array_filter(array_map('intval',$_POST['theme_ids']??[])));
  if(!in_array($di,['호재','중립','악재'],true))$di='중립';
  if(vd($ed)&&$ti!==''){
   $mysqli->begin_transaction();
   try{
    $s=$mysqli->prepare("INSERT INTO market_event(event_date,title,market_view,direction,calendar_id,memo) VALUES(?,?,NULLIF(?,''),?,?,NULLIF(?,''))");
    $s->bind_param('ssssis',$ed,$ti,$mv,$di,$cv,$me);$s->execute();$eid=$s->insert_id;$s->close();
    if($ids){$s=$mysqli->prepare("INSERT IGNORE INTO market_event_theme(event_id,theme_id) VALUES(?,?)");foreach($ids as $tid){$s->bind_param('ii',$eid,$tid);$s->execute();}$s->close();}
    $mysqli->commit();
   }catch(Throwable $e){$mysqli->rollback();throw $e;}
  }back($rd);
 }
 if($a==='event_delete'){
  $id=(int)$_POST['event_id'];$s=$mysqli->prepare("DELETE FROM market_event WHERE id=?");$s->bind_param('i',$id);$s->execute();$s->close();back($rd);
 }
 if($a==='calendar'){
  $ed=$_POST['event_date']??'';$tm=trim($_POST['event_time']??'');$ti=trim($_POST['title']??'');$im=max(1,min(3,(int)($_POST['importance']??2)));$me=trim($_POST['memo']??'');
  if(vd($ed)&&$ti!==''){$s=$mysqli->prepare("INSERT INTO market_calendar(event_date,event_time,title,importance,source_type,memo) VALUES(?,NULLIF(?,''),?,?,'MANUAL',NULLIF(?,''))");$s->bind_param('sssis',$ed,$tm,$ti,$im,$me);$s->execute();$s->close();}back($rd);
 }
}
$themes=rows($mysqli,"SELECT t.*,(SELECT COUNT(*) FROM market_event_theme x WHERE x.theme_id=t.id) cnt FROM market_theme t ORDER BY status='종료',sort_order,id");
$active=array_values(array_filter($themes,fn($x)=>$x['status']==='진행중'));
$cal=rows($mysqli,"SELECT * FROM market_calendar WHERE event_date>=? ORDER BY event_date,event_time,importance DESC LIMIT 40",'s',[$date]);
$where=$themeFilter?"EXISTS(SELECT 1 FROM market_event_theme z WHERE z.event_id=e.id AND z.theme_id=?)":"1=1";
$events=rows($mysqli,"SELECT e.*,c.title cal_title,GROUP_CONCAT(t.title ORDER BY t.sort_order SEPARATOR '||') tags FROM market_event e LEFT JOIN market_calendar c ON c.id=e.calendar_id LEFT JOIN market_event_theme x ON x.event_id=e.id LEFT JOIN market_theme t ON t.id=x.theme_id WHERE $where GROUP BY e.id ORDER BY e.event_date DESC,e.id DESC LIMIT 80",$themeFilter?'i':'',$themeFilter?[$themeFilter]:[]);
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><title>시장 흐름</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f5f8;color:#1f2937;font:13px Arial,'Malgun Gothic',sans-serif}a{text-decoration:none;color:inherit}input,select,button,textarea{font:inherit;border:1px solid #cfd5dc;border-radius:6px;padding:7px;background:#fff}.wrap{max-width:1800px;margin:auto;padding:14px 18px}.top,.card{background:#fff;border:1px solid #dfe3e8;border-radius:9px}.top{display:flex;align-items:center;gap:7px;padding:8px 11px;margin-bottom:9px}.title{font-size:19px;font-weight:800}.grow{flex:1}.btn{cursor:pointer;padding:6px 9px}.primary{background:#1f2937;color:#fff}.grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(380px,.65fr);gap:9px;align-items:start}.card{overflow:hidden;margin-bottom:9px}.head{padding:8px 11px;background:#fafafa;border-bottom:1px solid #e5e7eb;font-weight:700;display:flex;justify-content:space-between}.body{padding:9px 11px}.muted{font-size:11px;color:#94a3b8}.chips,.checks{display:flex;gap:6px;flex-wrap:wrap}.chip,.check{border:1px solid #cbd5e1;border-radius:15px;padding:5px 9px}.chip.on{background:#1f2937;color:#fff}.check input{padding:0;margin:0 3px 0 0}.r{display:grid;grid-template-columns:105px 1fr;gap:7px;align-items:center;margin-bottom:7px}.r>input,.r>select,.r>textarea{width:100%}.dirs{display:flex;gap:8px}.dirs input{margin-right:3px}.event{padding:9px 0;border-bottom:1px solid #eef1f4}.et{display:grid;grid-template-columns:82px 1fr auto;gap:7px}.ename{font-weight:800;font-size:14px}.view,.tags,.del{margin:5px 0 0 89px}.tag{display:inline-block;background:#f1f5f9;border-radius:12px;padding:3px 7px;margin-right:4px;font-size:11px}.badge{padding:3px 7px;border:1px solid;border-radius:12px;font-size:11px;font-weight:700}.good{color:#b91c1c;background:#fff1f2}.bad{color:#1d4ed8;background:#eff6ff}.neutral{color:#475569;background:#f8fafc}.schedule{display:grid;grid-template-columns:80px 50px 1fr auto;gap:6px;padding:6px 0;border-bottom:1px solid #eef1f4}.theme-row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #eef1f4}.note{line-height:1.75;color:#475569}@media(max-width:1100px){.grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
<div class="top"><strong class="title">시장 흐름</strong><form method="get"><input type="date" name="date" value="<?=h($date)?>"><button class="btn">이동</button></form><?php if(isset($_GET['saved'])):?><span style="color:#059669">저장됨</span><?php endif;?><span class="grow"></span><a class="btn" href="market_daily_review.php?date=<?=h($date)?>">일일 복기</a><a class="btn" href="market_question.php?date=<?=h($date)?>">시장 질문</a></div>
<div class="grid"><main>
<section class="card"><div class="head"><span>장기 테마</span><span class="muted">클릭하면 관련 이벤트만 조회</span></div><div class="body chips"><a class="chip <?=$themeFilter?'':'on'?>" href="?date=<?=h($date)?>">전체</a><?php foreach($active as $t):?><a class="chip <?=$themeFilter==(int)$t['id']?'on':''?>" href="?date=<?=h($date)?>&theme_id=<?=$t['id']?>"><?=h($t['title'])?> <span class="muted"><?=$t['cnt']?></span></a><?php endforeach;?></div></section>
<section class="card"><div class="head"><span>+ 새 시장 이벤트</span><span class="muted">기대·우려가 의미 있게 변했을 때만</span></div><div class="body"><form method="post"><input type="hidden" name="action" value="event"><input type="hidden" name="return_date" value="<?=h($date)?>">
<div class="r"><label>날짜</label><input type="date" name="event_date" value="<?=h($date)?>" required></div><div class="r"><label>이벤트</label><input name="title" placeholder="예: AI 투자 속도조절론 부각" required></div><div class="r"><label>시장 해석</label><input name="market_view" placeholder="예: AI 투자 피크아웃 우려"></div>
<div class="r"><label>방향</label><div class="dirs"><label><input type="radio" name="direction" value="호재">호재</label><label><input type="radio" name="direction" value="중립" checked>중립</label><label><input type="radio" name="direction" value="악재">악재</label></div></div>
<div class="r"><label>관련 테마</label><div class="checks"><?php foreach($active as $t):?><label class="check"><input type="checkbox" name="theme_ids[]" value="<?=$t['id']?>"><?=h($t['title'])?></label><?php endforeach;?></div></div>
<div class="r"><label>관련 일정</label><select name="calendar_id"><option value="0">없음 · 돌발/일반 이벤트</option><?php foreach($cal as $c):?><option value="<?=$c['id']?>"><?=h($c['event_date'].' '.($c['event_time']?substr($c['event_time'],0,5).' ':'').$c['title'])?></option><?php endforeach;?></select></div>
<div class="r"><label>메모</label><textarea name="memo" rows="2" placeholder="필요할 때만"></textarea></div><div style="text-align:right"><button class="btn primary">이벤트 등록</button></div></form></div></section>
<section class="card"><div class="head"><span>최근 시장 이벤트</span><span class="muted">이벤트 하나에 여러 테마 연결</span></div><div class="body"><?php foreach($events as $e):?><div class="event"><div class="et"><span><?=h($e['event_date'])?></span><span class="ename"><?=h($e['title'])?><?=$e['calendar_id']?'<small class="muted"> · 예정 일정에서 발생</small>':''?></span><span class="badge <?=dc($e['direction'])?>"><?=h($e['direction'])?></span></div><?php if($e['market_view']):?><div class="view">→ <?=h($e['market_view'])?></div><?php endif;?><div class="tags"><?php foreach(explode('||',$e['tags']??'') as $x)if($x!==''):?><span class="tag">#<?=h($x)?></span><?php endif;?></div><form class="del" method="post" onsubmit="return confirm('삭제하시겠습니까?')"><input type="hidden" name="action" value="event_delete"><input type="hidden" name="return_date" value="<?=h($date)?>"><input type="hidden" name="event_id" value="<?=$e['id']?>"><button class="btn">삭제</button></form></div><?php endforeach;?></div></section>
</main><aside>
<section class="card"><div class="head"><span>오늘 / 예정 일정</span></div><div class="body"><?php foreach($cal as $c):?><div class="schedule"><span><?=h($c['event_date'])?></span><span><?=$c['event_time']?h(substr($c['event_time'],0,5)):'-'?></span><strong><?=h($c['title'])?></strong><span><?=str_repeat('★',(int)$c['importance'])?></span></div><?php endforeach;?></div></section>
<section class="card"><div class="head"><span>일정 직접 추가</span><span class="muted">특수 일정 위주</span></div><div class="body"><form method="post"><input type="hidden" name="action" value="calendar"><input type="hidden" name="return_date" value="<?=h($date)?>"><div class="r"><label>날짜</label><input type="date" name="event_date" value="<?=h($date)?>" required></div><div class="r"><label>시간</label><input type="time" name="event_time"></div><div class="r"><label>일정</label><input name="title" placeholder="FOMC / 휴전협상" required></div><div class="r"><label>중요도</label><select name="importance"><option value="3">★★★</option><option value="2" selected>★★</option><option value="1">★</option></select></div><div class="r"><label>메모</label><input name="memo"></div><div style="text-align:right"><button class="btn primary">일정 등록</button></div></form></div></section>
<section class="card"><div class="head"><span>테마 관리</span><form method="post"><input type="hidden" name="action" value="theme"><input type="hidden" name="return_date" value="<?=h($date)?>"><input name="title" placeholder="새 장기 테마"><button class="btn">추가</button></form></div><div class="body"><?php foreach($themes as $t):?><div class="theme-row"><span><strong><?=h($t['title'])?></strong> <span class="muted">이벤트 <?=$t['cnt']?>건 · <?=h($t['status'])?></span></span><form method="post"><input type="hidden" name="action" value="theme_status"><input type="hidden" name="return_date" value="<?=h($date)?>"><input type="hidden" name="theme_id" value="<?=$t['id']?>"><input type="hidden" name="status" value="<?=$t['status']==='진행중'?'종료':'진행중'?>"><button class="btn"><?=$t['status']==='진행중'?'종료':'재활성'?></button></form></div><?php endforeach;?></div></section>
<section class="card"><div class="head">운영 기준</div><div class="body note"><strong>테마</strong>는 장기적으로 유지합니다.<br><strong>이벤트</strong>는 시장 기대·우려가 의미 있게 변했을 때만 기록합니다.<br>관련 테마는 여러 개 체크합니다.<br><strong>일정</strong> 결과가 중요하면 이벤트의 관련 일정으로 선택합니다.<br>돌발 뉴스는 일정 없이 바로 이벤트로 등록합니다.</div></section>
</aside></div></div></body></html>