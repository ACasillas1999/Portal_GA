<?php
declare(strict_types=1);

function wa_cfg(): array { static $c=null; if(!$c){ $c=require __DIR__.'/wa_config.php'; } return $c; }

function wa_normalize_phone(string $tel, string $country='+52'): string {
  $d=preg_replace('/\D+/','',$tel); if($d==='') return '';
  if ($d[0]==='0') $d=ltrim($d,'0');
  if (strpos($d, ltrim($country,'+')) === 0) return '+'.$d;
  if (substr($d,0,2)==='52' && strlen($d)===12) return '+'.$d;
  return $country.$d;
}

function wa_send_text(string $toRaw, string $bodyText): array {
  $cfg=wa_cfg(); $to=wa_normalize_phone($toRaw,$cfg['country_code']);
  if($to===''||trim($bodyText)==='') return ['ok'=>false,'err'=>'to/body vacío'];
  $url=$cfg['graph_url'].'/'.$cfg['phone_number_id'].'/messages';
  $payload=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['preview_url'=>false,'body'=>$bodyText]];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['access_token'],'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($err) return ['ok'=>false,'http'=>$http,'err'=>$err,'resp'=>$resp];
  $j=json_decode($resp,true); return ($http>=200&&$http<300)?['ok'=>true,'http'=>$http,'resp'=>$j]:['ok'=>false,'http'=>$http,'resp'=>$j];
}

function wa_send_template(string $toRaw, string $templateName, array $components=[], string $lang='es_MX'): array {
  $cfg=wa_cfg(); $to=wa_normalize_phone($toRaw,$cfg['country_code']); if($to==='') return ['ok'=>false,'err'=>'to vacío'];
  $url=$cfg['graph_url'].'/'.$cfg['phone_number_id'].'/messages';
  $payload=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'template','template'=>[
    'name'=>$templateName,'language'=>['code'=>$lang?:$cfg['default_lang']],'components'=>$components]];
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['access_token'],'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>15]);
  $resp=curl_exec($ch); $err=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($err) return ['ok'=>false,'http'=>$http,'err'=>$err,'resp'=>$resp];
  $j=json_decode($resp,true); return ($http>=200&&$http<300)?['ok'=>true,'http'=>$http,'resp'=>$j]:['ok'=>false,'http'=>$http,'resp'=>$j];
}
