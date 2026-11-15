<?php
/** Validated, consistently encoded employee portraits. */
class EmployeePhoto {
    public static function upload(array $file): ?string {
        $error=$file['error']??UPLOAD_ERR_NO_FILE;
        if ($error===UPLOAD_ERR_NO_FILE) return null;
        if ($error!==UPLOAD_ERR_OK) throw new InvalidArgumentException('The photo could not be uploaded. Choose a JPG or PNG up to 5 MB.');
        if (($file['size']??0)>5*1024*1024) throw new InvalidArgumentException('Choose a photo smaller than 5 MB.');
        $tmp=$file['tmp_name']??'';
        if (!is_uploaded_file($tmp)) throw new InvalidArgumentException('Please choose the photo again.');
        $info=@getimagesize($tmp);
        if (!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || $info[0]*$info[1]>20000000) throw new InvalidArgumentException('Choose a JPG or PNG photo up to 20 megapixels.');
        $source=$info[2]===IMAGETYPE_JPEG?@imagecreatefromjpeg($tmp):@imagecreatefrompng($tmp);
        if (!$source) throw new InvalidArgumentException('This photo could not be read. Choose another image.');
        $scale=min(1,1200/max($info[0],$info[1]));
        $image=imagecreatetruecolor(max(1,(int)($info[0]*$scale)),max(1,(int)($info[1]*$scale)));
        imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        imagecopyresampled($image,$source,0,0,0,0,imagesx($image),imagesy($image),$info[0],$info[1]);
        $name='portrait_'.bin2hex(random_bytes(16)).'.jpg';
        $saved=imagejpeg($image,UPLOAD_DIR.'/'.$name,88);
        imagedestroy($source);imagedestroy($image);
        if (!$saved) throw new RuntimeException('Could not save photo.');
        return 'storage/photos/'.$name;
    }
    public static function avatar(array $employee): string {
        $path=$employee['photo_path']??'';
        if (preg_match('~^storage/photos/[a-zA-Z0-9_.-]+$~',$path) && is_file(BASE_PATH.'/'.$path)) return '<img class="employee-avatar" src="'.e(APP_BASE_URL.'/'.$path).'" alt="" loading="lazy">';
        $words=preg_split('/\s+/u',trim($employee['name']??''));
        $initials=mb_substr($words[0]??'',0,1).(count($words)>1?mb_substr(end($words),0,1):'');
        return '<span class="employee-avatar" aria-hidden="true">'.e(mb_strtoupper($initials)).'</span>';
    }
}
