<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

/* Guard */
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['admin','karyawan'], true)) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

/* Helpers */
function jfail(string $m,int $c=400){ http_response_code($c); echo json_encode(['error'=>$m]); exit; }
function ok($d=null){ echo json_encode($d??['ok'=>true]); exit; }
function clean_str(?string $s,int $max=255){ $s=trim((string)$s); $s=preg_replace('/\s+/',' ',$s); return mb_substr($s,0,$max,'UTF-8'); }
function num($v):float{ return (float)number_format((float)$v,2,'.',''); }

/* Upload paths */
$UPLOAD_DIR = __DIR__ . '/../uploads/menu';
$PUBLIC_PREFIX = BASE_URL . '/backend/uploads/menu';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR,0755,true);

$act = $_GET['action'] ?? $_POST['action'] ?? '';

if ($act === 'list_catalog') {
  $rows=[]; $sql="SELECT id,name,category,image,price,stock_status FROM menu ORDER BY id ASC";
  if ($q=$conn->query($sql)) { while($r=$q->fetch_assoc()){
    $rows[]=[
      'id'=>(int)$r['id'],
      'name'=>(string)$r['name'],
      'category'=>(string)$r['category'],
      'image'=>(string)($r['image']??''),
      'image_path'=>$r['image']?$PUBLIC_PREFIX.'/'.rawurlencode($r['image']):null,
      'price'=>(float)$r['price'],
      'stock_status'=>(string)$r['stock_status'],
    ];
  } $q->close(); }
  ok($rows);
}

if ($act === 'add_catalog') {
  $name=clean_str($_POST['name']??'',150);
  $category=clean_str($_POST['category']??'',100);
  $price=num($_POST['price']??0);
  $stock=$_POST['stock_status']??'Ready';
  if ($name===''||$category===''||$price<0) jfail('Invalid fields');
  if (!in_array($stock,['Ready','Sold Out'],true)) $stock='Ready';

  $img=null;
  if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $f=$_FILES['image']; if ($f['error']!==UPLOAD_ERR_OK) jfail('Upload error '.$f['error']);
    $mime=mime_content_type($f['tmp_name']);
    if (!in_array($mime,['image/png','image/jpeg','image/webp'],true)) jfail('Invalid image type');
    if ($f['size']>1500000) jfail('Image > 1.5MB');
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)?:'jpg');
    $img='m_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!move_uploaded_file($f['tmp_name'],$UPLOAD_DIR.'/'.$img)) jfail('Save image failed',500);
  }

  $st=$conn->prepare("INSERT INTO menu (name,category,image,price,stock_status) VALUES (?,?,?,?,?)");
  $st->bind_param('sssds',$name,$category,$img,$price,$stock);
  if(!$st->execute()) jfail('DB insert failed',500);
  $id=$st->insert_id; $st->close();
  ok(['id'=>$id]);
}

if ($act === 'update_catalog') {
  $id=(int)($_POST['id']??0);
  $name=clean_str($_POST['name']??'',150);
  $category=clean_str($_POST['category']??'',100);
  $price=num($_POST['price']??0);
  $stock=$_POST['stock_status']??'Ready';
  if ($id<=0||$name===''||$category===''||$price<0) jfail('Invalid fields');
  if (!in_array($stock,['Ready','Sold Out'],true)) $stock='Ready';

  // old image
  $old=null; $q=$conn->prepare("SELECT image FROM menu WHERE id=?"); $q->bind_param('i',$id); $q->execute();
  $old=$q->get_result()->fetch_assoc()['image']??null; $q->close();

  $img=$old;
  if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $f=$_FILES['image']; if ($f['error']!==UPLOAD_ERR_OK) jfail('Upload error '.$f['error']);
    $mime=mime_content_type($f['tmp_name']);
    if (!in_array($mime,['image/png','image/jpeg','image/webp'],true)) jfail('Invalid image type');
    if ($f['size']>1500000) jfail('Image > 1.5MB');
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)?:'jpg');
    $img='m_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!move_uploaded_file($f['tmp_name'],$UPLOAD_DIR.'/'.$img)) jfail('Save image failed',500);
    if ($old && is_file($UPLOAD_DIR.'/'.$old)) @unlink($UPLOAD_DIR.'/'.$old);
  }

  $st=$conn->prepare("UPDATE menu SET name=?,category=?,image=?,price=?,stock_status=? WHERE id=?");
  $st->bind_param('sss dsi',$name,$category,$img,$price,$stock,$id); // <- TIDAK BOLEH ADA SPASI!
  // gunakan string tipe yang benar:
  $st->bind_param('sss dsi',$name,$category,$img,$price,$stock,$id);
}
?>