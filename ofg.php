<?php


error_reporting(E_ALL);

class ofgImage{

//    public static $maxThumbWidth = 700; // px

    public static $maxThumbHeight = 300; // px
    public static $maxThumbSizeCrop = 300; //px

    public static $croppedPrefix = "crp-";
    public static $scaledPrefix = "rsz-";

    /**
     * GD or IM
     * @var string
     */
    private $sImageLibary = "IM";

    public $iWidth = -1;
    public $iHeight = -1;

    public $iCroppedWidth = -1;
    public $iCroppedHeight = -1;

    public $iScaledWidth = -1;
    public $iScaledHeight = -1;

    private $sThumbDir = "";

    private $sFile = "";
    private $sResizedFile = "";
    private $sCroppedFile = "";

    /**
     * @return string
     */
    public function getThumbDir()
    {
        return $this->sThumbDir;
    }

    /**
     * @param string $sThumbDir
     */
    public function setSThumbDir($sThumbDir)
    {
        $this->sThumbDir = $sThumbDir."/";
        $this->setThumbFiles();
    }

    private function setThumbFiles(){
        $this->sCroppedFile = $this->sThumbDir.self::$croppedPrefix.$this->sFile;
        $this->sResizedFile = $this->sThumbDir.self::$scaledPrefix.$this->sFile;
    }

    public function sCroppedFile(){
        return $this->sCroppedFile;
    }
    public function sResizedFile(){
        return $this->sResizedFile;
    }
    public function sFile(){
        return $this->sFile;
    }
    public function width(){
        return $this->iWidth;
    }
    public function height(){
        return $this->iHeight;
    }
    public function croppedWidth(){
        return $this->iCroppedWidth;
    }
    public function croppedHeight(){
        return $this->iCroppedHeight;
    }
    public function resizedWidth(){
        return $this->iScaledWidth;
    }
    public function resizedHeight(){
        return $this->iScaledHeight;
    }

    public static function getFileExtension($sFile){
        $iDotPos = mb_strrpos($sFile,".");

        // file without .
        if($iDotPos === false)
            return "";


        $iDotPos++; // +1 for the dot
        $sExtension = mb_strcut($sFile,$iDotPos);
        $sExtension = strtolower($sExtension);

        return $sExtension;
    }

    public function humanFileSize(){
        $size = filesize($this->sFile);

        if ($size >= 1073741824) {
            $fileSize = round($size / 1024 / 1024 / 1024,1) . 'GB';
        } elseif ($size >= 1048576) {
            $fileSize = round($size / 1024 / 1024,1) . 'MB';
        } elseif($size >= 1024) {
            $fileSize = round($size / 1024,1) . 'KB';
        } else {
            $fileSize = $size . ' bytes';
        }
        return $fileSize;
    }

    public function __construct($sFile){
        $this->checkForGdAndIM();

        list($this->iWidth,$this->iHeight,$iType) = getimagesize($sFile);

        if($iType == 0){
            // unknown filetype
            return;
        }
        $this->sFile = $sFile;

        $this->setThumbFiles();

        if(file_exists($this->sCroppedFile))
            list($this->iCroppedWidth,$this->iCroppedHeight) = getimagesize($this->sCroppedFile);

        if(!file_exists($this->sResizedFile))
            list($this->iScaledWidth,$this->iScaledHeight) = getimagesize($this->sResizedFile);
    }

    public function resize(){

        if(file_exists($this->sResizedFile))
            return;

        $this->checkThumbdir();

        if($this->sImageLibary == "GD"){
            $this->_gdResize();
        }elseif($this->sImageLibary == "IM"){
            $this->_imResize();
        }
        list($this->iScaledWidth,$this->iScaledHeight) = getimagesize($this->sResizedFile);
    }

    public function crop(){

        if(file_exists($this->sCroppedFile))
            return;

        $this->checkThumbdir();

        if($this->sImageLibary == "GD"){
            $this->_gdCropImage();
        }elseif($this->sImageLibary == "IM"){
            $this->_imCrop();
        }
        list($this->iCroppedWidth,$this->iCroppedHeight) = getimagesize($this->sCroppedFile);
    }

    /*
     * functions to calculate new sizes (resize and crop)
     */


    /**
     * @param &$iCropWidth
     * @param &$iCropHeight
     * @param &$iCropCutX
     * @param &$iCropCutY
     */
    function calculateCropSize(&$iCropWidth, &$iCropHeight, &$iCropCutX, &$iCropCutY){
// calculate new sizes
        if($this->iHeight < $this->iWidth){ // landscape
            $iCropHeight = self::$maxThumbSizeCrop;
            $iCropWidth  = ($iCropHeight/$this->iHeight) * $this->iWidth;

            $iCropCutX   = ($iCropWidth - self::$maxThumbSizeCrop) / 2;
            $iCropCutY   = 0;
        }else{ // portrait
            $iCropWidth = self::$maxThumbSizeCrop;
            $iCropHeight = ($iCropWidth/$this->iWidth) * $this->iHeight;

            $iCropCutX   = 0;
            $iCropCutY   = ($iCropHeight - self::$maxThumbSizeCrop ) / 2;
        }
    }


    /**
     * @param $iNewWidth
     * @param $iNewHeight
     */
    function calculateResizeSize(&$iNewWidth, &$iNewHeight){
        // calculate new sizes
//        if($this->iHeight < $this->iWidth){ // landscape
//            $iNewWidth = self::$maxThumbWidth;
//            $iNewHeight = ($iNewWidth/$this->iWidth) * $this->iHeight;
//        }else{ // portrait
            $iNewHeight = self::$maxThumbHeight;
            $iNewWidth = ($iNewHeight/$this->iHeight) * $this->iWidth;
//        }
    }
    /********************************
     * Imagick functions
     ********************************/

    /**
     * @function    resizes the image to given size by using imagick
     */
    private function _imResize(){
        $this->calculateResizeSize($iNewX,$iNewY);

        $image = new Imagick($this->sFile);

        $image->resizeImage($iNewX,$iNewY,Imagick::FILTER_POINT,1);

        // save image
        $image->writeImage($this->sResizedFile);

        // free memonry
        $image->clear();
    }

    /**
     * @function    crops the image to given size by using imagick
     */
    private function _imCrop(){
        $image = new Imagick($this->sFile);
        $image->cropThumbnailImage(self::$maxThumbSizeCrop,self::$maxThumbSizeCrop);
        $image->writeImage($this->sCroppedFile);
        $image->clear();
    }

    /**
     * GD functions
     */

    /**
     * @function    resizes the image to given size by  using GD
     */
    private function _gdResize(){

        $this->calculateResizeSize($iNewWidth,$iNewHeight);

        $image = $this->_gdGetCreateImage();

        if($image === false)
            return;


        // Resize image / save / destroy
        $resizedImage = imagescale($image,$iNewWidth,$iNewHeight);
        $this->_gdSaveImage($resizedImage,$this->sResizedFile);
        imagedestroy($resizedImage);
        imagedestroy($image);
    }


    /**
     * @function    crops the image to given size by  using GD
     */
    private function _gdCropImage(){
        $this->calculateCropSize($iCropWidth,$iCropHeight,$iCropCutX,$iCropCutY);

        $image = $this->_gdGetCreateImage();

        if($image === false)
            return;

        // F**K this ...
        // $croppedImage = imagecropauto($image);

        // crop image
        $cropResizedImage = imagescale($image,$iCropWidth,$iCropHeight);
        // we don't need this one anymore
        imagedestroy($image);


        $croppedImage = imagecreatetruecolor(self::$maxThumbSizeCrop,self::$maxThumbSizeCrop);

        imagecopy($croppedImage,$cropResizedImage,
            0,0,
            $iCropCutX,
            $iCropCutY,
            self::$maxThumbSizeCrop,self::$maxThumbSizeCrop);

        $this->_gdSaveImage($croppedImage,$this->sCroppedFile);

        imagedestroy($croppedImage);
        imagedestroy($cropResizedImage);
    }

    /**
     * @function    creates an GD-image from given filename
     * @return      bool|resource
     */
    private function _gdGetCreateImage(){
        switch($this->getFileExtension($this->sFile))
        {
            case "jpg":
            case "jpeg":
                return imagecreatefromjpeg($this->sFile);
                break;

            case "png":
                return imagecreatefrompng($this->sFile);
                break;

            case "bmp":
                return imagecreatefromwbmp($this->sFile);
                break;

            case "gif":
                return imagecreatefromgif($this->sFile);
                break;

            default:
                return false;
        }
    }


    /**
     * @param $image|resource
     * @param $sFile|string
     * @return bool
     */
    private function _gdSaveImage($image, $sFile){

        switch($this->getFileExtension($sFile))
        {
            case "jpg":
            case "jpeg":
                return imagejpeg($image,$sFile);
                break;

            case "png":
                return imagepng($image,$sFile);
                break;

            case "bmp":
                return imagewbmp($image,$sFile);
                break;

            case "gif":
                return imagegif($image,$sFile);
                break;

            default:
                return false;
        }
    }


    /**
     * checks if lib-gd or lib-imagick is installed
     */
    private function checkForGdAndIM(){
        if(extension_loaded('gd'))
            $this->sImageLibary = "GD";

        // imagick prefered
        if (extension_loaded('imagick'))
            $this->sImageLibary = "IM";

        if($this->sImageLibary == "")
            exit("can not find lib-gd or lib-imagick");
    }

    /**
     * creates thumbdir if needed and test if it's writeable
     */
    private function checkThumbdir(){
        if(!is_dir($this->sThumbDir)){
            $oldMask = umask(0); // make it real 777
            if(!mkdir($this->sThumbDir,0777)){
                exit("can not create thumbdir: ".$this->sThumbDir);
            }
            umask($oldMask);
        }

        $sTestFile = $this->sThumbDir."/filerPermTest";
        if(!file_put_contents($sTestFile,"test")){
            exit("can not write to thumbdir: ".$this->sThumbDir);
        }

        if(!is_writable($sTestFile)){
            exit("thumbdir: ".$this->sThumbDir." is not writable!");
        }

        if(!unlink($sTestFile)){
            exit("can not delete file in thumbdir");
        }
    }
}

class ofg{

    /**
     * @var ofg
     */
    private static $oInstance;

    public $aFilesToHide = [

    ];

    public $aDirsToHide = [
        ".git",
        ".idea"
    ];

    public  $aValidImageTypes = [
        "png",
        "jpg",
        "jpeg",
        "bmp"
    ];


    /**
     * dir where thumbs are stored in
     * @var string
     */
    private $sThumbDir = ".ofgThumbs";


    private $sDir = "";


    private $aFiles = [];
    private $aDirs  = [];

    public static $sMaterializeCss = ".materialize-red{background-color:#e51c23 !important}.materialize-red-text{color:#e51c23 !important}.materialize-red.lighten-5{background-color:#fdeaeb !important}.materialize-red-text.text-lighten-5{color:#fdeaeb !important}.materialize-red.lighten-4{background-color:#f8c1c3 !important}.materialize-red-text.text-lighten-4{color:#f8c1c3 !important}.materialize-red.lighten-3{background-color:#f3989b !important}.materialize-red-text.text-lighten-3{color:#f3989b !important}.materialize-red.lighten-2{background-color:#ee6e73 !important}.materialize-red-text.text-lighten-2{color:#ee6e73 !important}.materialize-red.lighten-1{background-color:#ea454b !important}.materialize-red-text.text-lighten-1{color:#ea454b !important}.materialize-red.darken-1{background-color:#d0181e !important}.materialize-red-text.text-darken-1{color:#d0181e !important}.materialize-red.darken-2{background-color:#b9151b !important}.materialize-red-text.text-darken-2{color:#b9151b !important}.materialize-red.darken-3{background-color:#a21318 !important}.materialize-red-text.text-darken-3{color:#a21318 !important}.materialize-red.darken-4{background-color:#8b1014 !important}.materialize-red-text.text-darken-4{color:#8b1014 !important}.red{background-color:#F44336 !important}.red-text{color:#F44336 !important}.red.lighten-5{background-color:#FFEBEE !important}.red-text.text-lighten-5{color:#FFEBEE !important}.red.lighten-4{background-color:#FFCDD2 !important}.red-text.text-lighten-4{color:#FFCDD2 !important}.red.lighten-3{background-color:#EF9A9A !important}.red-text.text-lighten-3{color:#EF9A9A !important}.red.lighten-2{background-color:#E57373 !important}.red-text.text-lighten-2{color:#E57373 !important}.red.lighten-1{background-color:#EF5350 !important}.red-text.text-lighten-1{color:#EF5350 !important}.red.darken-1{background-color:#E53935 !important}.red-text.text-darken-1{color:#E53935 !important}.red.darken-2{background-color:#D32F2F !important}.red-text.text-darken-2{color:#D32F2F !important}.red.darken-3{background-color:#C62828 !important}.red-text.text-darken-3{color:#C62828 !important}.red.darken-4{background-color:#B71C1C !important}.red-text.text-darken-4{color:#B71C1C !important}.red.accent-1{background-color:#FF8A80 !important}.red-text.text-accent-1{color:#FF8A80 !important}.red.accent-2{background-color:#FF5252 !important}.red-text.text-accent-2{color:#FF5252 !important}.red.accent-3{background-color:#FF1744 !important}.red-text.text-accent-3{color:#FF1744 !important}.red.accent-4{background-color:#D50000 !important}.red-text.text-accent-4{color:#D50000 !important}.pink{background-color:#e91e63 !important}.pink-text{color:#e91e63 !important}.pink.lighten-5{background-color:#fce4ec !important}.pink-text.text-lighten-5{color:#fce4ec !important}.pink.lighten-4{background-color:#f8bbd0 !important}.pink-text.text-lighten-4{color:#f8bbd0 !important}.pink.lighten-3{background-color:#f48fb1 !important}.pink-text.text-lighten-3{color:#f48fb1 !important}.pink.lighten-2{background-color:#f06292 !important}.pink-text.text-lighten-2{color:#f06292 !important}.pink.lighten-1{background-color:#ec407a !important}.pink-text.text-lighten-1{color:#ec407a !important}.pink.darken-1{background-color:#d81b60 !important}.pink-text.text-darken-1{color:#d81b60 !important}.pink.darken-2{background-color:#c2185b !important}.pink-text.text-darken-2{color:#c2185b !important}.pink.darken-3{background-color:#ad1457 !important}.pink-text.text-darken-3{color:#ad1457 !important}.pink.darken-4{background-color:#880e4f !important}.pink-text.text-darken-4{color:#880e4f !important}.pink.accent-1{background-color:#ff80ab !important}.pink-text.text-accent-1{color:#ff80ab !important}.pink.accent-2{background-color:#ff4081 !important}.pink-text.text-accent-2{color:#ff4081 !important}.pink.accent-3{background-color:#f50057 !important}.pink-text.text-accent-3{color:#f50057 !important}.pink.accent-4{background-color:#c51162 !important}.pink-text.text-accent-4{color:#c51162 !important}.purple{background-color:#9c27b0 !important}.purple-text{color:#9c27b0 !important}.purple.lighten-5{background-color:#f3e5f5 !important}.purple-text.text-lighten-5{color:#f3e5f5 !important}.purple.lighten-4{background-color:#e1bee7 !important}.purple-text.text-lighten-4{color:#e1bee7 !important}.purple.lighten-3{background-color:#ce93d8 !important}.purple-text.text-lighten-3{color:#ce93d8 !important}.purple.lighten-2{background-color:#ba68c8 !important}.purple-text.text-lighten-2{color:#ba68c8 !important}.purple.lighten-1{background-color:#ab47bc !important}.purple-text.text-lighten-1{color:#ab47bc !important}.purple.darken-1{background-color:#8e24aa !important}.purple-text.text-darken-1{color:#8e24aa !important}.purple.darken-2{background-color:#7b1fa2 !important}.purple-text.text-darken-2{color:#7b1fa2 !important}.purple.darken-3{background-color:#6a1b9a !important}.purple-text.text-darken-3{color:#6a1b9a !important}.purple.darken-4{background-color:#4a148c !important}.purple-text.text-darken-4{color:#4a148c !important}.purple.accent-1{background-color:#ea80fc !important}.purple-text.text-accent-1{color:#ea80fc !important}.purple.accent-2{background-color:#e040fb !important}.purple-text.text-accent-2{color:#e040fb !important}.purple.accent-3{background-color:#d500f9 !important}.purple-text.text-accent-3{color:#d500f9 !important}.purple.accent-4{background-color:#a0f !important}.purple-text.text-accent-4{color:#a0f !important}.deep-purple{background-color:#673ab7 !important}.deep-purple-text{color:#673ab7 !important}.deep-purple.lighten-5{background-color:#ede7f6 !important}.deep-purple-text.text-lighten-5{color:#ede7f6 !important}.deep-purple.lighten-4{background-color:#d1c4e9 !important}.deep-purple-text.text-lighten-4{color:#d1c4e9 !important}.deep-purple.lighten-3{background-color:#b39ddb !important}.deep-purple-text.text-lighten-3{color:#b39ddb !important}.deep-purple.lighten-2{background-color:#9575cd !important}.deep-purple-text.text-lighten-2{color:#9575cd !important}.deep-purple.lighten-1{background-color:#7e57c2 !important}.deep-purple-text.text-lighten-1{color:#7e57c2 !important}.deep-purple.darken-1{background-color:#5e35b1 !important}.deep-purple-text.text-darken-1{color:#5e35b1 !important}.deep-purple.darken-2{background-color:#512da8 !important}.deep-purple-text.text-darken-2{color:#512da8 !important}.deep-purple.darken-3{background-color:#4527a0 !important}.deep-purple-text.text-darken-3{color:#4527a0 !important}.deep-purple.darken-4{background-color:#311b92 !important}.deep-purple-text.text-darken-4{color:#311b92 !important}.deep-purple.accent-1{background-color:#b388ff !important}.deep-purple-text.text-accent-1{color:#b388ff !important}.deep-purple.accent-2{background-color:#7c4dff !important}.deep-purple-text.text-accent-2{color:#7c4dff !important}.deep-purple.accent-3{background-color:#651fff !important}.deep-purple-text.text-accent-3{color:#651fff !important}.deep-purple.accent-4{background-color:#6200ea !important}.deep-purple-text.text-accent-4{color:#6200ea !important}.indigo{background-color:#3f51b5 !important}.indigo-text{color:#3f51b5 !important}.indigo.lighten-5{background-color:#e8eaf6 !important}.indigo-text.text-lighten-5{color:#e8eaf6 !important}.indigo.lighten-4{background-color:#c5cae9 !important}.indigo-text.text-lighten-4{color:#c5cae9 !important}.indigo.lighten-3{background-color:#9fa8da !important}.indigo-text.text-lighten-3{color:#9fa8da !important}.indigo.lighten-2{background-color:#7986cb !important}.indigo-text.text-lighten-2{color:#7986cb !important}.indigo.lighten-1{background-color:#5c6bc0 !important}.indigo-text.text-lighten-1{color:#5c6bc0 !important}.indigo.darken-1{background-color:#3949ab !important}.indigo-text.text-darken-1{color:#3949ab !important}.indigo.darken-2{background-color:#303f9f !important}.indigo-text.text-darken-2{color:#303f9f !important}.indigo.darken-3{background-color:#283593 !important}.indigo-text.text-darken-3{color:#283593 !important}.indigo.darken-4{background-color:#1a237e !important}.indigo-text.text-darken-4{color:#1a237e !important}.indigo.accent-1{background-color:#8c9eff !important}.indigo-text.text-accent-1{color:#8c9eff !important}.indigo.accent-2{background-color:#536dfe !important}.indigo-text.text-accent-2{color:#536dfe !important}.indigo.accent-3{background-color:#3d5afe !important}.indigo-text.text-accent-3{color:#3d5afe !important}.indigo.accent-4{background-color:#304ffe !important}.indigo-text.text-accent-4{color:#304ffe !important}.blue{background-color:#2196F3 !important}.blue-text{color:#2196F3 !important}.blue.lighten-5{background-color:#E3F2FD !important}.blue-text.text-lighten-5{color:#E3F2FD !important}.blue.lighten-4{background-color:#BBDEFB !important}.blue-text.text-lighten-4{color:#BBDEFB !important}.blue.lighten-3{background-color:#90CAF9 !important}.blue-text.text-lighten-3{color:#90CAF9 !important}.blue.lighten-2{background-color:#64B5F6 !important}.blue-text.text-lighten-2{color:#64B5F6 !important}.blue.lighten-1{background-color:#42A5F5 !important}.blue-text.text-lighten-1{color:#42A5F5 !important}.blue.darken-1{background-color:#1E88E5 !important}.blue-text.text-darken-1{color:#1E88E5 !important}.blue.darken-2{background-color:#1976D2 !important}.blue-text.text-darken-2{color:#1976D2 !important}.blue.darken-3{background-color:#1565C0 !important}.blue-text.text-darken-3{color:#1565C0 !important}.blue.darken-4{background-color:#0D47A1 !important}.blue-text.text-darken-4{color:#0D47A1 !important}.blue.accent-1{background-color:#82B1FF !important}.blue-text.text-accent-1{color:#82B1FF !important}.blue.accent-2{background-color:#448AFF !important}.blue-text.text-accent-2{color:#448AFF !important}.blue.accent-3{background-color:#2979FF !important}.blue-text.text-accent-3{color:#2979FF !important}.blue.accent-4{background-color:#2962FF !important}.blue-text.text-accent-4{color:#2962FF !important}.light-blue{background-color:#03a9f4 !important}.light-blue-text{color:#03a9f4 !important}.light-blue.lighten-5{background-color:#e1f5fe !important}.light-blue-text.text-lighten-5{color:#e1f5fe !important}.light-blue.lighten-4{background-color:#b3e5fc !important}.light-blue-text.text-lighten-4{color:#b3e5fc !important}.light-blue.lighten-3{background-color:#81d4fa !important}.light-blue-text.text-lighten-3{color:#81d4fa !important}.light-blue.lighten-2{background-color:#4fc3f7 !important}.light-blue-text.text-lighten-2{color:#4fc3f7 !important}.light-blue.lighten-1{background-color:#29b6f6 !important}.light-blue-text.text-lighten-1{color:#29b6f6 !important}.light-blue.darken-1{background-color:#039be5 !important}.light-blue-text.text-darken-1{color:#039be5 !important}.light-blue.darken-2{background-color:#0288d1 !important}.light-blue-text.text-darken-2{color:#0288d1 !important}.light-blue.darken-3{background-color:#0277bd !important}.light-blue-text.text-darken-3{color:#0277bd !important}.light-blue.darken-4{background-color:#01579b !important}.light-blue-text.text-darken-4{color:#01579b !important}.light-blue.accent-1{background-color:#80d8ff !important}.light-blue-text.text-accent-1{color:#80d8ff !important}.light-blue.accent-2{background-color:#40c4ff !important}.light-blue-text.text-accent-2{color:#40c4ff !important}.light-blue.accent-3{background-color:#00b0ff !important}.light-blue-text.text-accent-3{color:#00b0ff !important}.light-blue.accent-4{background-color:#0091ea !important}.light-blue-text.text-accent-4{color:#0091ea !important}.cyan{background-color:#00bcd4 !important}.cyan-text{color:#00bcd4 !important}.cyan.lighten-5{background-color:#e0f7fa !important}.cyan-text.text-lighten-5{color:#e0f7fa !important}.cyan.lighten-4{background-color:#b2ebf2 !important}.cyan-text.text-lighten-4{color:#b2ebf2 !important}.cyan.lighten-3{background-color:#80deea !important}.cyan-text.text-lighten-3{color:#80deea !important}.cyan.lighten-2{background-color:#4dd0e1 !important}.cyan-text.text-lighten-2{color:#4dd0e1 !important}.cyan.lighten-1{background-color:#26c6da !important}.cyan-text.text-lighten-1{color:#26c6da !important}.cyan.darken-1{background-color:#00acc1 !important}.cyan-text.text-darken-1{color:#00acc1 !important}.cyan.darken-2{background-color:#0097a7 !important}.cyan-text.text-darken-2{color:#0097a7 !important}.cyan.darken-3{background-color:#00838f !important}.cyan-text.text-darken-3{color:#00838f !important}.cyan.darken-4{background-color:#006064 !important}.cyan-text.text-darken-4{color:#006064 !important}.cyan.accent-1{background-color:#84ffff !important}.cyan-text.text-accent-1{color:#84ffff !important}.cyan.accent-2{background-color:#18ffff !important}.cyan-text.text-accent-2{color:#18ffff !important}.cyan.accent-3{background-color:#00e5ff !important}.cyan-text.text-accent-3{color:#00e5ff !important}.cyan.accent-4{background-color:#00b8d4 !important}.cyan-text.text-accent-4{color:#00b8d4 !important}.teal{background-color:#009688 !important}.teal-text{color:#009688 !important}.teal.lighten-5{background-color:#e0f2f1 !important}.teal-text.text-lighten-5{color:#e0f2f1 !important}.teal.lighten-4{background-color:#b2dfdb !important}.teal-text.text-lighten-4{color:#b2dfdb !important}.teal.lighten-3{background-color:#80cbc4 !important}.teal-text.text-lighten-3{color:#80cbc4 !important}.teal.lighten-2{background-color:#4db6ac !important}.teal-text.text-lighten-2{color:#4db6ac !important}.teal.lighten-1{background-color:#26a69a !important}.teal-text.text-lighten-1{color:#26a69a !important}.teal.darken-1{background-color:#00897b !important}.teal-text.text-darken-1{color:#00897b !important}.teal.darken-2{background-color:#00796b !important}.teal-text.text-darken-2{color:#00796b !important}.teal.darken-3{background-color:#00695c !important}.teal-text.text-darken-3{color:#00695c !important}.teal.darken-4{background-color:#004d40 !important}.teal-text.text-darken-4{color:#004d40 !important}.teal.accent-1{background-color:#a7ffeb !important}.teal-text.text-accent-1{color:#a7ffeb !important}.teal.accent-2{background-color:#64ffda !important}.teal-text.text-accent-2{color:#64ffda !important}.teal.accent-3{background-color:#1de9b6 !important}.teal-text.text-accent-3{color:#1de9b6 !important}.teal.accent-4{background-color:#00bfa5 !important}.teal-text.text-accent-4{color:#00bfa5 !important}.green{background-color:#4CAF50 !important}.green-text{color:#4CAF50 !important}.green.lighten-5{background-color:#E8F5E9 !important}.green-text.text-lighten-5{color:#E8F5E9 !important}.green.lighten-4{background-color:#C8E6C9 !important}.green-text.text-lighten-4{color:#C8E6C9 !important}.green.lighten-3{background-color:#A5D6A7 !important}.green-text.text-lighten-3{color:#A5D6A7 !important}.green.lighten-2{background-color:#81C784 !important}.green-text.text-lighten-2{color:#81C784 !important}.green.lighten-1{background-color:#66BB6A !important}.green-text.text-lighten-1{color:#66BB6A !important}.green.darken-1{background-color:#43A047 !important}.green-text.text-darken-1{color:#43A047 !important}.green.darken-2{background-color:#388E3C !important}.green-text.text-darken-2{color:#388E3C !important}.green.darken-3{background-color:#2E7D32 !important}.green-text.text-darken-3{color:#2E7D32 !important}.green.darken-4{background-color:#1B5E20 !important}.green-text.text-darken-4{color:#1B5E20 !important}.green.accent-1{background-color:#B9F6CA !important}.green-text.text-accent-1{color:#B9F6CA !important}.green.accent-2{background-color:#69F0AE !important}.green-text.text-accent-2{color:#69F0AE !important}.green.accent-3{background-color:#00E676 !important}.green-text.text-accent-3{color:#00E676 !important}.green.accent-4{background-color:#00C853 !important}.green-text.text-accent-4{color:#00C853 !important}.light-green{background-color:#8bc34a !important}.light-green-text{color:#8bc34a !important}.light-green.lighten-5{background-color:#f1f8e9 !important}.light-green-text.text-lighten-5{color:#f1f8e9 !important}.light-green.lighten-4{background-color:#dcedc8 !important}.light-green-text.text-lighten-4{color:#dcedc8 !important}.light-green.lighten-3{background-color:#c5e1a5 !important}.light-green-text.text-lighten-3{color:#c5e1a5 !important}.light-green.lighten-2{background-color:#aed581 !important}.light-green-text.text-lighten-2{color:#aed581 !important}.light-green.lighten-1{background-color:#9ccc65 !important}.light-green-text.text-lighten-1{color:#9ccc65 !important}.light-green.darken-1{background-color:#7cb342 !important}.light-green-text.text-darken-1{color:#7cb342 !important}.light-green.darken-2{background-color:#689f38 !important}.light-green-text.text-darken-2{color:#689f38 !important}.light-green.darken-3{background-color:#558b2f !important}.light-green-text.text-darken-3{color:#558b2f !important}.light-green.darken-4{background-color:#33691e !important}.light-green-text.text-darken-4{color:#33691e !important}.light-green.accent-1{background-color:#ccff90 !important}.light-green-text.text-accent-1{color:#ccff90 !important}.light-green.accent-2{background-color:#b2ff59 !important}.light-green-text.text-accent-2{color:#b2ff59 !important}.light-green.accent-3{background-color:#76ff03 !important}.light-green-text.text-accent-3{color:#76ff03 !important}.light-green.accent-4{background-color:#64dd17 !important}.light-green-text.text-accent-4{color:#64dd17 !important}.lime{background-color:#cddc39 !important}.lime-text{color:#cddc39 !important}.lime.lighten-5{background-color:#f9fbe7 !important}.lime-text.text-lighten-5{color:#f9fbe7 !important}.lime.lighten-4{background-color:#f0f4c3 !important}.lime-text.text-lighten-4{color:#f0f4c3 !important}.lime.lighten-3{background-color:#e6ee9c !important}.lime-text.text-lighten-3{color:#e6ee9c !important}.lime.lighten-2{background-color:#dce775 !important}.lime-text.text-lighten-2{color:#dce775 !important}.lime.lighten-1{background-color:#d4e157 !important}.lime-text.text-lighten-1{color:#d4e157 !important}.lime.darken-1{background-color:#c0ca33 !important}.lime-text.text-darken-1{color:#c0ca33 !important}.lime.darken-2{background-color:#afb42b !important}.lime-text.text-darken-2{color:#afb42b !important}.lime.darken-3{background-color:#9e9d24 !important}.lime-text.text-darken-3{color:#9e9d24 !important}.lime.darken-4{background-color:#827717 !important}.lime-text.text-darken-4{color:#827717 !important}.lime.accent-1{background-color:#f4ff81 !important}.lime-text.text-accent-1{color:#f4ff81 !important}.lime.accent-2{background-color:#eeff41 !important}.lime-text.text-accent-2{color:#eeff41 !important}.lime.accent-3{background-color:#c6ff00 !important}.lime-text.text-accent-3{color:#c6ff00 !important}.lime.accent-4{background-color:#aeea00 !important}.lime-text.text-accent-4{color:#aeea00 !important}.yellow{background-color:#ffeb3b !important}.yellow-text{color:#ffeb3b !important}.yellow.lighten-5{background-color:#fffde7 !important}.yellow-text.text-lighten-5{color:#fffde7 !important}.yellow.lighten-4{background-color:#fff9c4 !important}.yellow-text.text-lighten-4{color:#fff9c4 !important}.yellow.lighten-3{background-color:#fff59d !important}.yellow-text.text-lighten-3{color:#fff59d !important}.yellow.lighten-2{background-color:#fff176 !important}.yellow-text.text-lighten-2{color:#fff176 !important}.yellow.lighten-1{background-color:#ffee58 !important}.yellow-text.text-lighten-1{color:#ffee58 !important}.yellow.darken-1{background-color:#fdd835 !important}.yellow-text.text-darken-1{color:#fdd835 !important}.yellow.darken-2{background-color:#fbc02d !important}.yellow-text.text-darken-2{color:#fbc02d !important}.yellow.darken-3{background-color:#f9a825 !important}.yellow-text.text-darken-3{color:#f9a825 !important}.yellow.darken-4{background-color:#f57f17 !important}.yellow-text.text-darken-4{color:#f57f17 !important}.yellow.accent-1{background-color:#ffff8d !important}.yellow-text.text-accent-1{color:#ffff8d !important}.yellow.accent-2{background-color:#ff0 !important}.yellow-text.text-accent-2{color:#ff0 !important}.yellow.accent-3{background-color:#ffea00 !important}.yellow-text.text-accent-3{color:#ffea00 !important}.yellow.accent-4{background-color:#ffd600 !important}.yellow-text.text-accent-4{color:#ffd600 !important}.amber{background-color:#ffc107 !important}.amber-text{color:#ffc107 !important}.amber.lighten-5{background-color:#fff8e1 !important}.amber-text.text-lighten-5{color:#fff8e1 !important}.amber.lighten-4{background-color:#ffecb3 !important}.amber-text.text-lighten-4{color:#ffecb3 !important}.amber.lighten-3{background-color:#ffe082 !important}.amber-text.text-lighten-3{color:#ffe082 !important}.amber.lighten-2{background-color:#ffd54f !important}.amber-text.text-lighten-2{color:#ffd54f !important}.amber.lighten-1{background-color:#ffca28 !important}.amber-text.text-lighten-1{color:#ffca28 !important}.amber.darken-1{background-color:#ffb300 !important}.amber-text.text-darken-1{color:#ffb300 !important}.amber.darken-2{background-color:#ffa000 !important}.amber-text.text-darken-2{color:#ffa000 !important}.amber.darken-3{background-color:#ff8f00 !important}.amber-text.text-darken-3{color:#ff8f00 !important}.amber.darken-4{background-color:#ff6f00 !important}.amber-text.text-darken-4{color:#ff6f00 !important}.amber.accent-1{background-color:#ffe57f !important}.amber-text.text-accent-1{color:#ffe57f !important}.amber.accent-2{background-color:#ffd740 !important}.amber-text.text-accent-2{color:#ffd740 !important}.amber.accent-3{background-color:#ffc400 !important}.amber-text.text-accent-3{color:#ffc400 !important}.amber.accent-4{background-color:#ffab00 !important}.amber-text.text-accent-4{color:#ffab00 !important}.orange{background-color:#ff9800 !important}.orange-text{color:#ff9800 !important}.orange.lighten-5{background-color:#fff3e0 !important}.orange-text.text-lighten-5{color:#fff3e0 !important}.orange.lighten-4{background-color:#ffe0b2 !important}.orange-text.text-lighten-4{color:#ffe0b2 !important}.orange.lighten-3{background-color:#ffcc80 !important}.orange-text.text-lighten-3{color:#ffcc80 !important}.orange.lighten-2{background-color:#ffb74d !important}.orange-text.text-lighten-2{color:#ffb74d !important}.orange.lighten-1{background-color:#ffa726 !important}.orange-text.text-lighten-1{color:#ffa726 !important}.orange.darken-1{background-color:#fb8c00 !important}.orange-text.text-darken-1{color:#fb8c00 !important}.orange.darken-2{background-color:#f57c00 !important}.orange-text.text-darken-2{color:#f57c00 !important}.orange.darken-3{background-color:#ef6c00 !important}.orange-text.text-darken-3{color:#ef6c00 !important}.orange.darken-4{background-color:#e65100 !important}.orange-text.text-darken-4{color:#e65100 !important}.orange.accent-1{background-color:#ffd180 !important}.orange-text.text-accent-1{color:#ffd180 !important}.orange.accent-2{background-color:#ffab40 !important}.orange-text.text-accent-2{color:#ffab40 !important}.orange.accent-3{background-color:#ff9100 !important}.orange-text.text-accent-3{color:#ff9100 !important}.orange.accent-4{background-color:#ff6d00 !important}.orange-text.text-accent-4{color:#ff6d00 !important}.deep-orange{background-color:#ff5722 !important}.deep-orange-text{color:#ff5722 !important}.deep-orange.lighten-5{background-color:#fbe9e7 !important}.deep-orange-text.text-lighten-5{color:#fbe9e7 !important}.deep-orange.lighten-4{background-color:#ffccbc !important}.deep-orange-text.text-lighten-4{color:#ffccbc !important}.deep-orange.lighten-3{background-color:#ffab91 !important}.deep-orange-text.text-lighten-3{color:#ffab91 !important}.deep-orange.lighten-2{background-color:#ff8a65 !important}.deep-orange-text.text-lighten-2{color:#ff8a65 !important}.deep-orange.lighten-1{background-color:#ff7043 !important}.deep-orange-text.text-lighten-1{color:#ff7043 !important}.deep-orange.darken-1{background-color:#f4511e !important}.deep-orange-text.text-darken-1{color:#f4511e !important}.deep-orange.darken-2{background-color:#e64a19 !important}.deep-orange-text.text-darken-2{color:#e64a19 !important}.deep-orange.darken-3{background-color:#d84315 !important}.deep-orange-text.text-darken-3{color:#d84315 !important}.deep-orange.darken-4{background-color:#bf360c !important}.deep-orange-text.text-darken-4{color:#bf360c !important}.deep-orange.accent-1{background-color:#ff9e80 !important}.deep-orange-text.text-accent-1{color:#ff9e80 !important}.deep-orange.accent-2{background-color:#ff6e40 !important}.deep-orange-text.text-accent-2{color:#ff6e40 !important}.deep-orange.accent-3{background-color:#ff3d00 !important}.deep-orange-text.text-accent-3{color:#ff3d00 !important}.deep-orange.accent-4{background-color:#dd2c00 !important}.deep-orange-text.text-accent-4{color:#dd2c00 !important}.brown{background-color:#795548 !important}.brown-text{color:#795548 !important}.brown.lighten-5{background-color:#efebe9 !important}.brown-text.text-lighten-5{color:#efebe9 !important}.brown.lighten-4{background-color:#d7ccc8 !important}.brown-text.text-lighten-4{color:#d7ccc8 !important}.brown.lighten-3{background-color:#bcaaa4 !important}.brown-text.text-lighten-3{color:#bcaaa4 !important}.brown.lighten-2{background-color:#a1887f !important}.brown-text.text-lighten-2{color:#a1887f !important}.brown.lighten-1{background-color:#8d6e63 !important}.brown-text.text-lighten-1{color:#8d6e63 !important}.brown.darken-1{background-color:#6d4c41 !important}.brown-text.text-darken-1{color:#6d4c41 !important}.brown.darken-2{background-color:#5d4037 !important}.brown-text.text-darken-2{color:#5d4037 !important}.brown.darken-3{background-color:#4e342e !important}.brown-text.text-darken-3{color:#4e342e !important}.brown.darken-4{background-color:#3e2723 !important}.brown-text.text-darken-4{color:#3e2723 !important}.blue-grey{background-color:#607d8b !important}.blue-grey-text{color:#607d8b !important}.blue-grey.lighten-5{background-color:#eceff1 !important}.blue-grey-text.text-lighten-5{color:#eceff1 !important}.blue-grey.lighten-4{background-color:#cfd8dc !important}.blue-grey-text.text-lighten-4{color:#cfd8dc !important}.blue-grey.lighten-3{background-color:#b0bec5 !important}.blue-grey-text.text-lighten-3{color:#b0bec5 !important}.blue-grey.lighten-2{background-color:#90a4ae !important}.blue-grey-text.text-lighten-2{color:#90a4ae !important}.blue-grey.lighten-1{background-color:#78909c !important}.blue-grey-text.text-lighten-1{color:#78909c !important}.blue-grey.darken-1{background-color:#546e7a !important}.blue-grey-text.text-darken-1{color:#546e7a !important}.blue-grey.darken-2{background-color:#455a64 !important}.blue-grey-text.text-darken-2{color:#455a64 !important}.blue-grey.darken-3{background-color:#37474f !important}.blue-grey-text.text-darken-3{color:#37474f !important}.blue-grey.darken-4{background-color:#263238 !important}.blue-grey-text.text-darken-4{color:#263238 !important}.grey{background-color:#9e9e9e !important}.grey-text{color:#9e9e9e !important}.grey.lighten-5{background-color:#fafafa !important}.grey-text.text-lighten-5{color:#fafafa !important}.grey.lighten-4{background-color:#f5f5f5 !important}.grey-text.text-lighten-4{color:#f5f5f5 !important}.grey.lighten-3{background-color:#eee !important}.grey-text.text-lighten-3{color:#eee !important}.grey.lighten-2{background-color:#e0e0e0 !important}.grey-text.text-lighten-2{color:#e0e0e0 !important}.grey.lighten-1{background-color:#bdbdbd !important}.grey-text.text-lighten-1{color:#bdbdbd !important}.grey.darken-1{background-color:#757575 !important}.grey-text.text-darken-1{color:#757575 !important}.grey.darken-2{background-color:#616161 !important}.grey-text.text-darken-2{color:#616161 !important}.grey.darken-3{background-color:#424242 !important}.grey-text.text-darken-3{color:#424242 !important}.grey.darken-4{background-color:#212121 !important}.grey-text.text-darken-4{color:#212121 !important}.black{background-color:#000 !important}.black-text{color:#000 !important}.white{background-color:#fff !important}.white-text{color:#fff !important}.transparent{background-color:transparent !important}.transparent-text{color:transparent !important}/*! normalize.css v3.0.3 | MIT License | github.com/necolas/normalize.css */html{font-family:sans-serif;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,hgroup,main,menu,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block;vertical-align:baseline}audio:not([controls]){display:none;height:0}[hidden],template{display:none}a{background-color:transparent}a:active,a:hover{outline:0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}dfn{font-style:italic}h1{font-size:2em;margin:0.67em 0}mark{background:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}img{border:0}svg:not(:root){overflow:hidden}figure{margin:1em 40px}hr{box-sizing:content-box;height:0}pre{overflow:auto}code,kbd,pre,samp{font-family:monospace, monospace;font-size:1em}button,input,optgroup,select,textarea{color:inherit;font:inherit;margin:0}button{overflow:visible}button,select{text-transform:none}button,html input[type=\"button\"],input[type=\"reset\"],input[type=\"submit\"]{-webkit-appearance:button;cursor:pointer}button[disabled],html input[disabled]{cursor:default}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}input{line-height:normal}input[type=\"checkbox\"],input[type=\"radio\"]{box-sizing:border-box;padding:0}input[type=\"number\"]::-webkit-inner-spin-button,input[type=\"number\"]::-webkit-outer-spin-button{height:auto}input[type=\"search\"]{-webkit-appearance:textfield;box-sizing:content-box}input[type=\"search\"]::-webkit-search-cancel-button,input[type=\"search\"]::-webkit-search-decoration{-webkit-appearance:none}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:0.35em 0.625em 0.75em}legend{border:0;padding:0}textarea{overflow:auto}optgroup{font-weight:bold}table{border-collapse:collapse;border-spacing:0}td,th{padding:0}html{box-sizing:border-box}*,*:before,*:after{box-sizing:inherit}ul{padding:0;list-style-type:none}ul.browser-default,ul.browser-default li{list-style-type:initial}ul li{list-style-type:none}a{color:#039be5;text-decoration:none;-webkit-tap-highlight-color:transparent}.valign-wrapper{display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-align-items:center;-ms-flex-align:center;align-items:center}.valign-wrapper .valign{display:block}.clearfix{clear:both}.z-depth-0{box-shadow:none !important}.z-depth-1,nav,.card-panel,.card,.toast,.btn,.btn-large,.btn-floating,.dropdown-content,.collapsible,.side-nav{box-shadow:0 2px 5px 0 rgba(0,0,0,0.16),0 2px 10px 0 rgba(0,0,0,0.12)}.z-depth-1-half,.btn:hover,.btn-large:hover,.btn-floating:hover{box-shadow:0 5px 11px 0 rgba(0,0,0,0.18),0 4px 15px 0 rgba(0,0,0,0.15)}.z-depth-2{box-shadow:0 8px 17px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19)}.z-depth-3{box-shadow:0 12px 15px 0 rgba(0,0,0,0.24),0 17px 50px 0 rgba(0,0,0,0.19)}.z-depth-4,.modal{box-shadow:0 16px 28px 0 rgba(0,0,0,0.22),0 25px 55px 0 rgba(0,0,0,0.21)}.z-depth-5{box-shadow:0 27px 24px 0 rgba(0,0,0,0.2),0 40px 77px 0 rgba(0,0,0,0.22)}.hoverable{transition:box-shadow .25s;box-shadow:0}.hoverable:hover{transition:box-shadow .25s;box-shadow:0 8px 17px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19)}.divider{height:1px;overflow:hidden;background-color:#e0e0e0}blockquote{margin:20px 0;padding-left:1.5rem;border-left:5px solid #ee6e73}i{line-height:inherit}i.left{float:left;margin-right:15px}i.right{float:right;margin-left:15px}i.tiny{font-size:1rem}i.small{font-size:2rem}i.medium{font-size:4rem}i.large{font-size:6rem}img.responsive-img,video.responsive-video{max-width:100%;height:auto}.pagination li{display:inline-block;border-radius:2px;text-align:center;vertical-align:top;height:30px}.pagination li a{color:#444;display:inline-block;font-size:1.2rem;padding:0 10px;line-height:30px}.pagination li.active a{color:#fff}.pagination li.active{background-color:#ee6e73}.pagination li.disabled a{cursor:default;color:#999}.pagination li i{font-size:2rem}.pagination li.pages ul li{display:inline-block;float:none}@media only screen and (max-width: 992px){.pagination{width:100%}.pagination li.prev,.pagination li.next{width:10%}.pagination li.pages{width:80%;overflow:hidden;white-space:nowrap}}.breadcrumb{font-size:18px;color:rgba(255,255,255,0.7)}.breadcrumb i,.breadcrumb [class^=\"mdi-\"],.breadcrumb [class*=\"mdi-\"],.breadcrumb i.material-icons{display:inline-block;float:left;font-size:24px}.breadcrumb:before{content:'\E5CC';color:rgba(255,255,255,0.7);vertical-align:top;display:inline-block;font-family:'Material Icons';font-weight:normal;font-style:normal;font-size:25px;margin:0 10px 0 8px;-webkit-font-smoothing:antialiased}.breadcrumb:first-child:before{display:none}.breadcrumb:last-child{color:#fff}.parallax-container{position:relative;overflow:hidden;height:500px}.parallax{position:absolute;top:0;left:0;right:0;bottom:0;z-index:-1}.parallax img{display:none;position:absolute;left:50%;bottom:0;min-width:100%;min-height:100%;-webkit-transform:translate3d(0, 0, 0);transform:translate3d(0, 0, 0);-webkit-transform:translateX(-50%);transform:translateX(-50%)}.pin-top,.pin-bottom{position:relative}.pinned{position:fixed !important}ul.staggered-list li{opacity:0}.fade-in{opacity:0;-webkit-transform-origin:0 50%;transform-origin:0 50%}@media only screen and (max-width: 600px){.hide-on-small-only,.hide-on-small-and-down{display:none !important}}@media only screen and (max-width: 992px){.hide-on-med-and-down{display:none !important}}@media only screen and (min-width: 601px){.hide-on-med-and-up{display:none !important}}@media only screen and (min-width: 600px) and (max-width: 992px){.hide-on-med-only{display:none !important}}@media only screen and (min-width: 993px){.hide-on-large-only{display:none !important}}@media only screen and (min-width: 993px){.show-on-large{display:block !important}}@media only screen and (min-width: 600px) and (max-width: 992px){.show-on-medium{display:block !important}}@media only screen and (max-width: 600px){.show-on-small{display:block !important}}@media only screen and (min-width: 601px){.show-on-medium-and-up{display:block !important}}@media only screen and (max-width: 992px){.show-on-medium-and-down{display:block !important}}@media only screen and (max-width: 600px){.center-on-small-only{text-align:center}}footer.page-footer{margin-top:20px;padding-top:20px;background-color:#ee6e73}footer.page-footer .footer-copyright{overflow:hidden;height:50px;line-height:50px;color:rgba(255,255,255,0.8);background-color:rgba(51,51,51,0.08)}table,th,td{border:none}table{width:100%;display:table}table.bordered>thead>tr,table.bordered>tbody>tr{border-bottom:1px solid #d0d0d0}table.striped>tbody>tr:nth-child(odd){background-color:#f2f2f2}table.striped>tbody>tr>td{border-radius:0}table.highlight>tbody>tr{transition:background-color .25s ease}table.highlight>tbody>tr:hover{background-color:#f2f2f2}table.centered thead tr th,table.centered tbody tr td{text-align:center}thead{border-bottom:1px solid #d0d0d0}td,th{padding:15px 5px;display:table-cell;text-align:left;vertical-align:middle;border-radius:2px}@media only screen and (max-width: 992px){table.responsive-table{width:100%;border-collapse:collapse;border-spacing:0;display:block;position:relative}table.responsive-table td:empty:before{content:'\00a0'}table.responsive-table th,table.responsive-table td{margin:0;vertical-align:top}table.responsive-table th{text-align:left}table.responsive-table thead{display:block;float:left}table.responsive-table thead tr{display:block;padding:0 10px 0 0}table.responsive-table thead tr th::before{content:\"\00a0\"}table.responsive-table tbody{display:block;width:auto;position:relative;overflow-x:auto;white-space:nowrap}table.responsive-table tbody tr{display:inline-block;vertical-align:top}table.responsive-table th{display:block;text-align:right}table.responsive-table td{display:block;min-height:1.25em;text-align:left}table.responsive-table tr{padding:0 10px}table.responsive-table thead{border:0;border-right:1px solid #d0d0d0}table.responsive-table.bordered th{border-bottom:0;border-left:0}table.responsive-table.bordered td{border-left:0;border-right:0;border-bottom:0}table.responsive-table.bordered tr{border:0}table.responsive-table.bordered tbody tr{border-right:1px solid #d0d0d0}}.collection{margin:0.5rem 0 1rem 0;border:1px solid #e0e0e0;border-radius:2px;overflow:hidden;position:relative}.collection .collection-item{background-color:#fff;line-height:1.5rem;padding:10px 20px;margin:0;border-bottom:1px solid #e0e0e0}.collection .collection-item.avatar{min-height:84px;padding-left:72px;position:relative}.collection .collection-item.avatar .circle{position:absolute;width:42px;height:42px;overflow:hidden;left:15px;display:inline-block;vertical-align:middle}.collection .collection-item.avatar i.circle{font-size:18px;line-height:42px;color:#fff;background-color:#999;text-align:center}.collection .collection-item.avatar .title{font-size:16px}.collection .collection-item.avatar p{margin:0}.collection .collection-item.avatar .secondary-content{position:absolute;top:16px;right:16px}.collection .collection-item:last-child{border-bottom:none}.collection .collection-item.active{background-color:#26a69a;color:#eafaf9}.collection .collection-item.active .secondary-content{color:#fff}.collection a.collection-item{display:block;transition:.25s;color:#26a69a}.collection a.collection-item:not(.active):hover{background-color:#ddd}.collection.with-header .collection-header{background-color:#fff;border-bottom:1px solid #e0e0e0;padding:10px 20px}.collection.with-header .collection-item{padding-left:30px}.collection.with-header .collection-item.avatar{padding-left:72px}.secondary-content{float:right;color:#26a69a}.collapsible .collection{margin:0;border:none}span.badge{min-width:3rem;padding:0 6px;text-align:center;font-size:1rem;line-height:inherit;color:#757575;position:absolute;right:15px;box-sizing:border-box}span.badge.new{font-weight:300;font-size:0.8rem;color:#fff;background-color:#26a69a;border-radius:2px}span.badge.new:after{content:\" new\"}span.badge[data-badge-caption]::after{content:\" \" attr(data-badge-caption)}nav ul a span.badge{position:static;margin-left:4px;line-height:0}.video-container{position:relative;padding-bottom:56.25%;height:0;overflow:hidden}.video-container iframe,.video-container object,.video-container embed{position:absolute;top:0;left:0;width:100%;height:100%}.progress{position:relative;height:4px;display:block;width:100%;background-color:#acece6;border-radius:2px;margin:0.5rem 0 1rem 0;overflow:hidden}.progress .determinate{position:absolute;top:0;left:0;bottom:0;background-color:#26a69a;transition:width .3s linear}.progress .indeterminate{background-color:#26a69a}.progress .indeterminate:before{content:'';position:absolute;background-color:inherit;top:0;left:0;bottom:0;will-change:left, right;-webkit-animation:indeterminate 2.1s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite;animation:indeterminate 2.1s cubic-bezier(0.65, 0.815, 0.735, 0.395) infinite}.progress .indeterminate:after{content:'';position:absolute;background-color:inherit;top:0;left:0;bottom:0;will-change:left, right;-webkit-animation:indeterminate-short 2.1s cubic-bezier(0.165, 0.84, 0.44, 1) infinite;animation:indeterminate-short 2.1s cubic-bezier(0.165, 0.84, 0.44, 1) infinite;-webkit-animation-delay:1.15s;animation-delay:1.15s}@-webkit-keyframes indeterminate{0%{left:-35%;right:100%}60%{left:100%;right:-90%}100%{left:100%;right:-90%}}@keyframes indeterminate{0%{left:-35%;right:100%}60%{left:100%;right:-90%}100%{left:100%;right:-90%}}@-webkit-keyframes indeterminate-short{0%{left:-200%;right:100%}60%{left:107%;right:-8%}100%{left:107%;right:-8%}}@keyframes indeterminate-short{0%{left:-200%;right:100%}60%{left:107%;right:-8%}100%{left:107%;right:-8%}}.hide{display:none !important}.left-align{text-align:left}.right-align{text-align:right}.center,.center-align{text-align:center}.left{float:left !important}.right{float:right !important}.no-select,input[type=range],input[type=range]+.thumb{-webkit-touch-callout:none;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}.circle{border-radius:50%}.center-block{display:block;margin-left:auto;margin-right:auto}.truncate{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.no-padding{padding:0 !important}.material-icons{text-rendering:optimizeLegibility;-webkit-font-feature-settings:'liga';-moz-font-feature-settings:'liga';font-feature-settings:'liga'}.container{margin:0 auto;max-width:1280px;width:90%}@media only screen and (min-width: 601px){.container{width:85%}}@media only screen and (min-width: 993px){.container{width:70%}}.container .row{margin-left:-0.75rem;margin-right:-0.75rem}.section{padding-top:1rem;padding-bottom:1rem}.section.no-pad{padding:0}.section.no-pad-bot{padding-bottom:0}.section.no-pad-top{padding-top:0}.row{margin-left:auto;margin-right:auto;margin-bottom:20px}.row:after{content:\"\";display:table;clear:both}.row .col{float:left;box-sizing:border-box;padding:0 0.75rem;min-height:1px}.row .col[class*=\"push-\"],.row .col[class*=\"pull-\"]{position:relative}.row .col.s1{width:8.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.s2{width:16.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.s3{width:25%;margin-left:auto;left:auto;right:auto}.row .col.s4{width:33.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.s5{width:41.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.s6{width:50%;margin-left:auto;left:auto;right:auto}.row .col.s7{width:58.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.s8{width:66.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.s9{width:75%;margin-left:auto;left:auto;right:auto}.row .col.s10{width:83.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.s11{width:91.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.s12{width:100%;margin-left:auto;left:auto;right:auto}.row .col.offset-s1{margin-left:8.3333333333%}.row .col.pull-s1{right:8.3333333333%}.row .col.push-s1{left:8.3333333333%}.row .col.offset-s2{margin-left:16.6666666667%}.row .col.pull-s2{right:16.6666666667%}.row .col.push-s2{left:16.6666666667%}.row .col.offset-s3{margin-left:25%}.row .col.pull-s3{right:25%}.row .col.push-s3{left:25%}.row .col.offset-s4{margin-left:33.3333333333%}.row .col.pull-s4{right:33.3333333333%}.row .col.push-s4{left:33.3333333333%}.row .col.offset-s5{margin-left:41.6666666667%}.row .col.pull-s5{right:41.6666666667%}.row .col.push-s5{left:41.6666666667%}.row .col.offset-s6{margin-left:50%}.row .col.pull-s6{right:50%}.row .col.push-s6{left:50%}.row .col.offset-s7{margin-left:58.3333333333%}.row .col.pull-s7{right:58.3333333333%}.row .col.push-s7{left:58.3333333333%}.row .col.offset-s8{margin-left:66.6666666667%}.row .col.pull-s8{right:66.6666666667%}.row .col.push-s8{left:66.6666666667%}.row .col.offset-s9{margin-left:75%}.row .col.pull-s9{right:75%}.row .col.push-s9{left:75%}.row .col.offset-s10{margin-left:83.3333333333%}.row .col.pull-s10{right:83.3333333333%}.row .col.push-s10{left:83.3333333333%}.row .col.offset-s11{margin-left:91.6666666667%}.row .col.pull-s11{right:91.6666666667%}.row .col.push-s11{left:91.6666666667%}.row .col.offset-s12{margin-left:100%}.row .col.pull-s12{right:100%}.row .col.push-s12{left:100%}@media only screen and (min-width: 601px){.row .col.m1{width:8.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.m2{width:16.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.m3{width:25%;margin-left:auto;left:auto;right:auto}.row .col.m4{width:33.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.m5{width:41.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.m6{width:50%;margin-left:auto;left:auto;right:auto}.row .col.m7{width:58.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.m8{width:66.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.m9{width:75%;margin-left:auto;left:auto;right:auto}.row .col.m10{width:83.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.m11{width:91.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.m12{width:100%;margin-left:auto;left:auto;right:auto}.row .col.offset-m1{margin-left:8.3333333333%}.row .col.pull-m1{right:8.3333333333%}.row .col.push-m1{left:8.3333333333%}.row .col.offset-m2{margin-left:16.6666666667%}.row .col.pull-m2{right:16.6666666667%}.row .col.push-m2{left:16.6666666667%}.row .col.offset-m3{margin-left:25%}.row .col.pull-m3{right:25%}.row .col.push-m3{left:25%}.row .col.offset-m4{margin-left:33.3333333333%}.row .col.pull-m4{right:33.3333333333%}.row .col.push-m4{left:33.3333333333%}.row .col.offset-m5{margin-left:41.6666666667%}.row .col.pull-m5{right:41.6666666667%}.row .col.push-m5{left:41.6666666667%}.row .col.offset-m6{margin-left:50%}.row .col.pull-m6{right:50%}.row .col.push-m6{left:50%}.row .col.offset-m7{margin-left:58.3333333333%}.row .col.pull-m7{right:58.3333333333%}.row .col.push-m7{left:58.3333333333%}.row .col.offset-m8{margin-left:66.6666666667%}.row .col.pull-m8{right:66.6666666667%}.row .col.push-m8{left:66.6666666667%}.row .col.offset-m9{margin-left:75%}.row .col.pull-m9{right:75%}.row .col.push-m9{left:75%}.row .col.offset-m10{margin-left:83.3333333333%}.row .col.pull-m10{right:83.3333333333%}.row .col.push-m10{left:83.3333333333%}.row .col.offset-m11{margin-left:91.6666666667%}.row .col.pull-m11{right:91.6666666667%}.row .col.push-m11{left:91.6666666667%}.row .col.offset-m12{margin-left:100%}.row .col.pull-m12{right:100%}.row .col.push-m12{left:100%}}@media only screen and (min-width: 993px){.row .col.l1{width:8.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.l2{width:16.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.l3{width:25%;margin-left:auto;left:auto;right:auto}.row .col.l4{width:33.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.l5{width:41.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.l6{width:50%;margin-left:auto;left:auto;right:auto}.row .col.l7{width:58.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.l8{width:66.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.l9{width:75%;margin-left:auto;left:auto;right:auto}.row .col.l10{width:83.3333333333%;margin-left:auto;left:auto;right:auto}.row .col.l11{width:91.6666666667%;margin-left:auto;left:auto;right:auto}.row .col.l12{width:100%;margin-left:auto;left:auto;right:auto}.row .col.offset-l1{margin-left:8.3333333333%}.row .col.pull-l1{right:8.3333333333%}.row .col.push-l1{left:8.3333333333%}.row .col.offset-l2{margin-left:16.6666666667%}.row .col.pull-l2{right:16.6666666667%}.row .col.push-l2{left:16.6666666667%}.row .col.offset-l3{margin-left:25%}.row .col.pull-l3{right:25%}.row .col.push-l3{left:25%}.row .col.offset-l4{margin-left:33.3333333333%}.row .col.pull-l4{right:33.3333333333%}.row .col.push-l4{left:33.3333333333%}.row .col.offset-l5{margin-left:41.6666666667%}.row .col.pull-l5{right:41.6666666667%}.row .col.push-l5{left:41.6666666667%}.row .col.offset-l6{margin-left:50%}.row .col.pull-l6{right:50%}.row .col.push-l6{left:50%}.row .col.offset-l7{margin-left:58.3333333333%}.row .col.pull-l7{right:58.3333333333%}.row .col.push-l7{left:58.3333333333%}.row .col.offset-l8{margin-left:66.6666666667%}.row .col.pull-l8{right:66.6666666667%}.row .col.push-l8{left:66.6666666667%}.row .col.offset-l9{margin-left:75%}.row .col.pull-l9{right:75%}.row .col.push-l9{left:75%}.row .col.offset-l10{margin-left:83.3333333333%}.row .col.pull-l10{right:83.3333333333%}.row .col.push-l10{left:83.3333333333%}.row .col.offset-l11{margin-left:91.6666666667%}.row .col.pull-l11{right:91.6666666667%}.row .col.push-l11{left:91.6666666667%}.row .col.offset-l12{margin-left:100%}.row .col.pull-l12{right:100%}.row .col.push-l12{left:100%}}nav{color:#fff;background-color:#ee6e73;width:100%;height:56px;line-height:56px}nav a{color:#fff}nav i,nav [class^=\"mdi-\"],nav [class*=\"mdi-\"],nav i.material-icons{display:block;font-size:2rem;height:56px;line-height:56px}nav .nav-wrapper{position:relative;height:100%}@media only screen and (min-width: 993px){nav a.button-collapse{display:none}}nav .button-collapse{float:left;position:relative;z-index:1;height:56px}nav .button-collapse i{font-size:2.7rem;height:56px;line-height:56px}nav .brand-logo{position:absolute;color:#fff;display:inline-block;font-size:2.1rem;padding:0;white-space:nowrap}nav .brand-logo.center{left:50%;-webkit-transform:translateX(-50%);transform:translateX(-50%)}@media only screen and (max-width: 992px){nav .brand-logo{left:50%;-webkit-transform:translateX(-50%);transform:translateX(-50%)}nav .brand-logo.left,nav .brand-logo.right{padding:0;-webkit-transform:none;transform:none}nav .brand-logo.left{left:0.5rem}nav .brand-logo.right{right:0.5rem;left:auto}}nav .brand-logo.right{right:0.5rem;padding:0}nav .brand-logo i,nav .brand-logo [class^=\"mdi-\"],nav .brand-logo [class*=\"mdi-\"],nav .brand-logo i.material-icons{float:left;margin-right:15px}nav ul{margin:0}nav ul li{transition:background-color .3s;float:left;padding:0}nav ul li.active{background-color:rgba(0,0,0,0.1)}nav ul a{transition:background-color .3s;font-size:1rem;color:#fff;display:block;padding:0 15px;cursor:pointer}nav ul a.btn,nav ul a.btn-large,nav ul a.btn-large,nav ul a.btn-flat,nav ul a.btn-floating{margin-top:-2px;margin-left:15px;margin-right:15px}nav ul a:hover{background-color:rgba(0,0,0,0.1)}nav ul.left{float:left}nav form{height:100%}nav .input-field{margin:0;height:100%}nav .input-field input{height:100%;font-size:1.2rem;border:none;padding-left:2rem}nav .input-field input:focus,nav .input-field input[type=text]:valid,nav .input-field input[type=password]:valid,nav .input-field input[type=email]:valid,nav .input-field input[type=url]:valid,nav .input-field input[type=date]:valid{border:none;box-shadow:none}nav .input-field label{top:0;left:0}nav .input-field label i{color:rgba(255,255,255,0.7);transition:color .3s}nav .input-field label.active i{color:#fff}nav .input-field label.active{-webkit-transform:translateY(0);transform:translateY(0)}.navbar-fixed{position:relative;height:56px;z-index:998}.navbar-fixed nav{position:fixed}@media only screen and (min-width: 601px){nav,nav .nav-wrapper i,nav a.button-collapse,nav a.button-collapse i{height:64px;line-height:64px}.navbar-fixed{height:64px}}@font-face{font-family:\"Roboto\";src:local(Roboto Thin),url(\"../fonts/roboto/Roboto-Thin.eot\");src:url(\"../fonts/roboto/Roboto-Thin.eot?#iefix\") format(\"embedded-opentype\"),url(\"../fonts/roboto/Roboto-Thin.woff2\") format(\"woff2\"),url(\"../fonts/roboto/Roboto-Thin.woff\") format(\"woff\"),url(\"../fonts/roboto/Roboto-Thin.ttf\") format(\"truetype\");font-weight:200}@font-face{font-family:\"Roboto\";src:local(Roboto Light),url(\"../fonts/roboto/Roboto-Light.eot\");src:url(\"../fonts/roboto/Roboto-Light.eot?#iefix\") format(\"embedded-opentype\"),url(\"../fonts/roboto/Roboto-Light.woff2\") format(\"woff2\"),url(\"../fonts/roboto/Roboto-Light.woff\") format(\"woff\"),url(\"../fonts/roboto/Roboto-Light.ttf\") format(\"truetype\");font-weight:300}@font-face{font-family:\"Roboto\";src:local(Roboto Regular),url(\"../fonts/roboto/Roboto-Regular.eot\");src:url(\"../fonts/roboto/Roboto-Regular.eot?#iefix\") format(\"embedded-opentype\"),url(\"../fonts/roboto/Roboto-Regular.woff2\") format(\"woff2\"),url(\"../fonts/roboto/Roboto-Regular.woff\") format(\"woff\"),url(\"../fonts/roboto/Roboto-Regular.ttf\") format(\"truetype\");font-weight:400}@font-face{font-family:\"Roboto\";src:url(\"../fonts/roboto/Roboto-Medium.eot\");src:url(\"../fonts/roboto/Roboto-Medium.eot?#iefix\") format(\"embedded-opentype\"),url(\"../fonts/roboto/Roboto-Medium.woff2\") format(\"woff2\"),url(\"../fonts/roboto/Roboto-Medium.woff\") format(\"woff\"),url(\"../fonts/roboto/Roboto-Medium.ttf\") format(\"truetype\");font-weight:500}@font-face{font-family:\"Roboto\";src:url(\"../fonts/roboto/Roboto-Bold.eot\");src:url(\"../fonts/roboto/Roboto-Bold.eot?#iefix\") format(\"embedded-opentype\"),url(\"../fonts/roboto/Roboto-Bold.woff2\") format(\"woff2\"),url(\"../fonts/roboto/Roboto-Bold.woff\") format(\"woff\"),url(\"../fonts/roboto/Roboto-Bold.ttf\") format(\"truetype\");font-weight:700}a{text-decoration:none}html{line-height:1.5;font-family:\"Roboto\", sans-serif;font-weight:normal;color:rgba(0,0,0,0.87)}@media only screen and (min-width: 0){html{font-size:14px}}@media only screen and (min-width: 992px){html{font-size:14.5px}}@media only screen and (min-width: 1200px){html{font-size:15px}}h1,h2,h3,h4,h5,h6{font-weight:400;line-height:1.1}h1 a,h2 a,h3 a,h4 a,h5 a,h6 a{font-weight:inherit}h1{font-size:4.2rem;line-height:110%;margin:2.1rem 0 1.68rem 0}h2{font-size:3.56rem;line-height:110%;margin:1.78rem 0 1.424rem 0}h3{font-size:2.92rem;line-height:110%;margin:1.46rem 0 1.168rem 0}h4{font-size:2.28rem;line-height:110%;margin:1.14rem 0 0.912rem 0}h5{font-size:1.64rem;line-height:110%;margin:0.82rem 0 0.656rem 0}h6{font-size:1rem;line-height:110%;margin:0.5rem 0 0.4rem 0}em{font-style:italic}strong{font-weight:500}small{font-size:75%}.light,footer.page-footer .footer-copyright{font-weight:300}.thin{font-weight:200}.flow-text{font-weight:300}@media only screen and (min-width: 360px){.flow-text{font-size:1.2rem}}@media only screen and (min-width: 390px){.flow-text{font-size:1.224rem}}@media only screen and (min-width: 420px){.flow-text{font-size:1.248rem}}@media only screen and (min-width: 450px){.flow-text{font-size:1.272rem}}@media only screen and (min-width: 480px){.flow-text{font-size:1.296rem}}@media only screen and (min-width: 510px){.flow-text{font-size:1.32rem}}@media only screen and (min-width: 540px){.flow-text{font-size:1.344rem}}@media only screen and (min-width: 570px){.flow-text{font-size:1.368rem}}@media only screen and (min-width: 600px){.flow-text{font-size:1.392rem}}@media only screen and (min-width: 630px){.flow-text{font-size:1.416rem}}@media only screen and (min-width: 660px){.flow-text{font-size:1.44rem}}@media only screen and (min-width: 690px){.flow-text{font-size:1.464rem}}@media only screen and (min-width: 720px){.flow-text{font-size:1.488rem}}@media only screen and (min-width: 750px){.flow-text{font-size:1.512rem}}@media only screen and (min-width: 780px){.flow-text{font-size:1.536rem}}@media only screen and (min-width: 810px){.flow-text{font-size:1.56rem}}@media only screen and (min-width: 840px){.flow-text{font-size:1.584rem}}@media only screen and (min-width: 870px){.flow-text{font-size:1.608rem}}@media only screen and (min-width: 900px){.flow-text{font-size:1.632rem}}@media only screen and (min-width: 930px){.flow-text{font-size:1.656rem}}@media only screen and (min-width: 960px){.flow-text{font-size:1.68rem}}@media only screen and (max-width: 360px){.flow-text{font-size:1.2rem}}.card-panel{transition:box-shadow .25s;padding:20px;margin:0.5rem 0 1rem 0;border-radius:2px;background-color:#fff}.card{position:relative;margin:0.5rem 0 1rem 0;background-color:#fff;transition:box-shadow .25s;border-radius:2px}.card .card-title{font-size:24px;font-weight:300}.card .card-title.activator{cursor:pointer}.card.small,.card.medium,.card.large{position:relative}.card.small .card-image,.card.medium .card-image,.card.large .card-image{max-height:60%;overflow:hidden}.card.small .card-image+.card-content,.card.medium .card-image+.card-content,.card.large .card-image+.card-content{max-height:40%}.card.small .card-content,.card.medium .card-content,.card.large .card-content{max-height:100%;overflow:hidden}.card.small .card-action,.card.medium .card-action,.card.large .card-action{position:absolute;bottom:0;left:0;right:0}.card.small{height:300px}.card.medium{height:400px}.card.large{height:500px}.card.horizontal{display:-webkit-flex;display:-ms-flexbox;display:flex}.card.horizontal.small .card-image,.card.horizontal.medium .card-image,.card.horizontal.large .card-image{height:100%;max-height:none;overflow:visible}.card.horizontal.small .card-image img,.card.horizontal.medium .card-image img,.card.horizontal.large .card-image img{height:100%}.card.horizontal .card-image{max-width:50%}.card.horizontal .card-image img{max-width:100%;width:auto}.card.horizontal .card-stacked{display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-flex-direction:column;-ms-flex-direction:column;flex-direction:column;-webkit-flex:1;-ms-flex:1;flex:1;position:relative}.card.horizontal .card-stacked .card-content{-webkit-flex-grow:1;-ms-flex-positive:1;flex-grow:1}.card.sticky-action .card-action{z-index:2}.card.sticky-action .card-reveal{z-index:1;padding-bottom:64px}.card .card-image{position:relative}.card .card-image img{display:block;border-radius:2px 2px 0 0;position:relative;left:0;right:0;top:0;bottom:0;width:100%}.card .card-image .card-title{color:#fff;position:absolute;bottom:0;left:0;padding:20px}.card .card-content{padding:20px;border-radius:0 0 2px 2px}.card .card-content p{margin:0;color:inherit}.card .card-content .card-title{line-height:48px}.card .card-action{position:relative;background-color:inherit;border-top:1px solid rgba(160,160,160,0.2);padding:20px}.card .card-action a:not(.btn):not(.btn-large):not(.btn-floating){color:#ffab40;margin-right:20px;transition:color .3s ease;text-transform:uppercase}.card .card-action a:not(.btn):not(.btn-large):not(.btn-floating):hover{color:#ffd8a6}.card .card-reveal{padding:20px;position:absolute;background-color:#fff;width:100%;overflow-y:auto;top:100%;height:100%;z-index:3;display:none}.card .card-reveal .card-title{cursor:pointer;display:block}#toast-container{display:block;position:fixed;z-index:10000}@media only screen and (max-width: 600px){#toast-container{min-width:100%;bottom:0%}}@media only screen and (min-width: 601px) and (max-width: 992px){#toast-container{left:5%;bottom:7%;max-width:90%}}@media only screen and (min-width: 993px){#toast-container{top:10%;right:7%;max-width:86%}}.toast{border-radius:2px;top:0;width:auto;clear:both;margin-top:10px;position:relative;max-width:100%;height:auto;min-height:48px;line-height:1.5em;word-break:break-all;background-color:#323232;padding:10px 25px;font-size:1.1rem;font-weight:300;color:#fff;display:-webkit-flex;display:-ms-flexbox;display:flex;-webkit-align-items:center;-ms-flex-align:center;align-items:center;-webkit-justify-content:space-between;-ms-flex-pack:justify;justify-content:space-between}.toast .btn,.toast .btn-large,.toast .btn-flat{margin:0;margin-left:3rem}.toast.rounded{border-radius:24px}@media only screen and (max-width: 600px){.toast{width:100%;border-radius:0}}@media only screen and (min-width: 601px) and (max-width: 992px){.toast{float:left}}@media only screen and (min-width: 993px){.toast{float:right}}.tabs{display:-webkit-flex;display:-ms-flexbox;display:flex;position:relative;overflow-x:auto;overflow-y:hidden;height:48px;background-color:#fff;margin:0 auto;width:100%;white-space:nowrap}.tabs .tab{-webkit-box-flex:1;-webkit-flex-grow:1;-ms-flex-positive:1;flex-grow:1;display:block;float:left;text-align:center;line-height:48px;height:48px;padding:0;margin:0;text-transform:uppercase;text-overflow:ellipsis;overflow:hidden;letter-spacing:.8px;width:15%;min-width:80px}.tabs .tab a{color:#ee6e73;display:block;width:100%;height:100%;text-overflow:ellipsis;overflow:hidden;transition:color .28s ease}.tabs .tab a:hover{color:#f9c9cb}.tabs .tab.disabled a{color:#f9c9cb;cursor:default}.tabs .indicator{position:absolute;bottom:0;height:2px;background-color:#f6b2b5;will-change:left, right}.material-tooltip{padding:10px 8px;font-size:1rem;z-index:2000;background-color:transparent;border-radius:2px;color:#fff;min-height:36px;line-height:120%;opacity:0;display:none;position:absolute;text-align:center;max-width:calc(100% - 4px);overflow:hidden;left:0;top:0;pointer-events:none}.backdrop{position:absolute;opacity:0;display:none;height:7px;width:14px;border-radius:0 0 50% 50%;background-color:#323232;z-index:-1;-webkit-transform-origin:50% 0%;transform-origin:50% 0%;-webkit-transform:translate3d(0, 0, 0);transform:translate3d(0, 0, 0)}.btn,.btn-large,.btn-flat{border:none;border-radius:2px;display:inline-block;height:36px;line-height:36px;outline:0;padding:0 2rem;text-transform:uppercase;vertical-align:middle;-webkit-tap-highlight-color:transparent}.btn.disabled,.disabled.btn-large,.btn-floating.disabled,.btn-large.disabled,.btn:disabled,.btn-large:disabled,.btn-large:disabled,.btn-floating:disabled,.btn[disabled],[disabled].btn-large,.btn-large[disabled],.btn-floating[disabled]{background-color:#DFDFDF !important;box-shadow:none;color:#9F9F9F !important;cursor:default}.btn.disabled *,.disabled.btn-large *,.btn-floating.disabled *,.btn-large.disabled *,.btn:disabled *,.btn-large:disabled *,.btn-large:disabled *,.btn-floating:disabled *,.btn[disabled] *,[disabled].btn-large *,.btn-large[disabled] *,.btn-floating[disabled] *{pointer-events:none}.btn.disabled:hover,.disabled.btn-large:hover,.btn-floating.disabled:hover,.btn-large.disabled:hover,.btn:disabled:hover,.btn-large:disabled:hover,.btn-large:disabled:hover,.btn-floating:disabled:hover,.btn[disabled]:hover,[disabled].btn-large:hover,.btn-large[disabled]:hover,.btn-floating[disabled]:hover{background-color:#DFDFDF !important;color:#9F9F9F !important}.btn i,.btn-large i,.btn-floating i,.btn-large i,.btn-flat i{font-size:1.3rem;line-height:inherit}.btn,.btn-large{text-decoration:none;color:#fff;background-color:#26a69a;text-align:center;letter-spacing:.5px;transition:.2s ease-out;cursor:pointer}.btn:hover,.btn-large:hover{background-color:#2bbbad}.btn-floating{display:inline-block;color:#fff;position:relative;overflow:hidden;z-index:1;width:37px;height:37px;line-height:37px;padding:0;background-color:#26a69a;border-radius:50%;transition:.3s;cursor:pointer;vertical-align:middle}.btn-floating i{width:inherit;display:inline-block;text-align:center;color:#fff;font-size:1.6rem;line-height:37px}.btn-floating:hover{background-color:#26a69a}.btn-floating:before{border-radius:0}.btn-floating.btn-large{width:55.5px;height:55.5px}.btn-floating.btn-large i{line-height:55.5px}button.btn-floating{border:none}.fixed-action-btn{position:fixed;right:23px;bottom:23px;padding-top:15px;margin-bottom:0;z-index:998}.fixed-action-btn.active ul{visibility:visible}.fixed-action-btn.horizontal{padding:0 0 0 15px}.fixed-action-btn.horizontal ul{text-align:right;right:64px;top:50%;-webkit-transform:translateY(-50%);transform:translateY(-50%);height:100%;left:auto;width:500px}.fixed-action-btn.horizontal ul li{display:inline-block;margin:15px 15px 0 0}.fixed-action-btn ul{left:0;right:0;text-align:center;position:absolute;bottom:64px;margin:0;visibility:hidden}.fixed-action-btn ul li{margin-bottom:15px}.fixed-action-btn ul a.btn-floating{opacity:0}.btn-flat{box-shadow:none;background-color:transparent;color:#343434;cursor:pointer;transition:background-color .2s}.btn-flat:focus,.btn-flat:active{background-color:transparent}.btn-flat:hover{background-color:rgba(0,0,0,0.1);box-shadow:none}.btn-flat.disabled{color:#b3b3b3;cursor:default}.btn-large{height:54px;line-height:54px}.btn-large i{font-size:1.6rem}.btn-block{display:block}.dropdown-content{background-color:#fff;margin:0;display:none;min-width:100px;max-height:650px;overflow-y:auto;opacity:0;position:absolute;z-index:999;will-change:width, height}.dropdown-content li{clear:both;color:rgba(0,0,0,0.87);cursor:pointer;min-height:50px;line-height:1.5rem;width:100%;text-align:left;text-transform:none}.dropdown-content li:hover,.dropdown-content li.active,.dropdown-content li.selected{background-color:#eee}.dropdown-content li.active.selected{background-color:#e1e1e1}.dropdown-content li.divider{min-height:0;height:1px}.dropdown-content li>a,.dropdown-content li>span{font-size:16px;color:#26a69a;display:block;line-height:22px;padding:14px 16px}.dropdown-content li>span>label{top:1px;left:3px;height:18px}.dropdown-content li>a>i{height:inherit;line-height:inherit}



.thumb{
    margin: 0 auto;
    display: inline-block;
}
.row{
    margin-bottom: 0;
    line-height: 0;
}

.icon{
    height: 48px;
    width: 48px;
    display: inline-block;
}

@media all and (orientation:portrait) {
    #col{
        width: 100%; /* .m10 = 83.333333% */
        margin-left: 0; /* .offset-m1 = 8.333333% */
    }
}

.icon-grid{
    background-image:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAQAAAD9CzEMAAAAMElEQVR4Ae3TIRIAAARFQfe/NF0kMbua8tIP4J1st/0JCFwMzFmygIAlW7KAgCUDBejq/R/ICoT7AAAAAElFTkSuQmCC');
}
.icon-list{
    background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAQAAAD9CzEMAAAALElEQVR4Ae3TIRYAAAREQfe/NEHQ94kzkvTTFu8Aeu/+8PKAAHYggB0I5AAY/u9+kPhbxLQAAAAASUVORK5CYII=');
}
.icon-rows{
    background-image:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAQAAAD9CzEMAAAAMklEQVRYw+3UwQ0AAAQEQf03TQ/ig1kFTHIPEZKulYMHAAAAXeDuN+3MBwAAAH8BSTsrnQy8boszz1EAAAAASUVORK5CYII=');
}
.js-only{
     display: none;
 }

/* sticky footer */
body {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
}

body > .row {
    flex: 1;
    margin: 0;
}

footer.page-footer{
    padding-top: 0;
    margin-top: 0;
}    ";

    /**
     * ofg constructor.
     * @param $bStatic
     */
    public function __construct($bStatic = false)
    {

        if($bStatic){
            return $this;
        }

        $oInstance = self::getInstance();

        $oInstance->getDir();
        $oInstance->scanDir();

        // just send the CSS
        if(isset($_GET["file"]) && $_GET["file"]=="materialize.min.css"){
            header("Content-Type: text/css");
            header("X-Content-Type-Options: nosniff");
            echo self::$sMaterializeCss;
            exit();
        }

        // just send the js
        if(isset($_GET["file"]) && $_GET["file"]=="ofg.js"){
            $oInstance->sendJs();
        }

        $oInstance->getHtml();

        return $oInstance;
    }

    /**
     * @function returns the instance of ofg, use it to set variables like thumb-dir etc
     * @return ofg
     */
    private static function getInstance(){
        if( !self::$oInstance ) {
            self::$oInstance = new self(true);
        }
        return self::$oInstance;
    }


    /**
     * @function set sThumbDir
     * @param $sDir
     */
    public static function setThumbDir($sDir){
        self::getInstance()->sThumbDir = $sDir;
    }

    /**
     * @function gets the directory to parse
     *           removes all "../"
     * @return string
     */
    function getDir(){
        $sDir = isset($_GET["dir"])?$_GET["dir"]:"/";


        while(strpos($sDir,"../") !== false){
            $sDir = str_replace("../","",$sDir);
        }

        $sDir = ".".$sDir;

        $this->sDir = $sDir;

        return $sDir;
    }


    private function scanDir(){
        if($this->sDir == ""){
            exit("set dir before scanning");
        }

        $aFiles = scandir($this->sDir);

        // don't show ./ and ../ and thumbdir
        $this->aDirsToHide[] = ".";
        $this->aDirsToHide[] = "..";
        $this->aDirsToHide[] = $this->sThumbDir;

        foreach($aFiles as $sDirEntry){
            if(is_dir($sDirEntry)){
                // add to dir-array if not not to hide
                if(!in_array($sDirEntry,$this->aDirsToHide))
                    $this->aDirs[] = $sDirEntry;
            }else if(is_file($sDirEntry)) {

                // add to files-array if not to hide and valid image-type
                if(!in_array($sDirEntry,$this->aFilesToHide)
                    && in_array(ofgImage::getFileExtension($sDirEntry),$this->aValidImageTypes)){
                    $oImage = new  ofgImage($sDirEntry);
                    $oImage->setSThumbDir($this->sThumbDir);
                    $oImage->resize();
                    $oImage->crop();
                    $this->aFiles[] = $oImage;
                }
            }else {
                // wtf??
            }
        }
    }


    public $htmlTplListTpl = '<ul class="collection">@LIST</ul>';
    public $htmlTplListImg =
        '<li class="collection-item avatar">
            <a href="@BIGSRC">
                <img src="@SRC" class="circle medium">
                <p>@TITLE</p>
                <p>@INFO</p>
            </a>
        </li>';

    public $htmlTplGripTpl = '<div class="col s12">@LIST</div>';
    public $htmlTplGridImg = "<a href='@BIGSRC'><img src='@SRC' class='nojs'></a>";

    private function getView(){
        if(isset($_GET["view"])){
            $sView = strtolower($_GET["view"]);
            if($sView == "grid"){
                setcookie("view","grid");
                return "grid";
            }elseif($sView=="list"){
                setcookie("view","list");
                return "list";
            }
        }
        if(isset($_COOKIE["view"])){
            $sView = strtolower($_COOKIE["view"]);
            if($sView == "grid"){
                return "grid";
            }elseif($sView=="list"){
                return "list";
            }
        }
        return "grid";
    }

    public function getHtml(){
        header("Content-Type: text/html; charset=utf-8");
        $sOutput = "
  <!DOCTYPE html>
  <html>
    <head>
      <link type=\"text/css\" rel=\"stylesheet\" href=\"?file=materialize.min.css\"  media=\"screen,projection\"/>

      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"/>
      <title>ofg - one file gallery</title>
    </head>
    <body class='grey darken-2'>
     <nav>
    <div class=\"nav-wrapper grey darken-1\">
      <a href=\"#\" class=\"brand-logo\">Logo</a>
      <ul id=\"viewIcon\" class=\"right\">";
        if($this->getView()=="grid"){
            $sOutput .= "<li><a href=\"?view=list\" class='icon icon-list'></a></li>";
        }else{
            $sOutput .= "<li><a href=\"?view=grid\" class='icon icon-grid'></a></li>";
        }
      $sOutput .="</ul>
    </div>
  </nav>

    <div class='row'>
        <div class='col s12 m10 offset-m1' id='col'>
    ";


        $sRowTpl = "";
        $sImgTpl = "";

        $sTmp = "";
        /**
         * @var $oFile ofgImage
         */
        if(($sView = $this->getView())=="grid"){
            $sRowTpl = $this->htmlTplGripTpl;
            $sImgTpl = $this->htmlTplGridImg;
        }elseif($sView=="list"){
            $sRowTpl = $this->htmlTplListTpl;
            $sImgTpl = $this->htmlTplListImg;

        }
        foreach($this->aFiles as $oFile){
            $sHtmlInfo = $oFile->width()."x".$oFile->height()."px / ".$oFile->humanFileSize();
            $sTmp .= str_replace(["@BIGSRC","@SRC","@TITLE","@INFO"],
                [$oFile->sFile(),$oFile->sCroppedFile(),$oFile->sFile(),$sHtmlInfo],
                $sImgTpl);
        }

        $sOutput .= str_replace("@LIST",$sTmp,$sRowTpl);


        $sOutput .="
        </div>
    </div>
        ";

        $sOutput .= "

        <footer class=\"page-footer grey darken-2\">
          <div class=\"footer-copyright\">
            <div class=\"container center-align\">

            <a class=\"grey-text text-lighten-2 \" href=\"https://github.com/littleCdev/ofg\">
            ofg - one file gallery - © 2016 Matthias Böck
            </a>
            </div>
          </div>
        </footer>

      <script type=\"application/javascript\" src=\"?file=ofg.js\"></script>
    </body></html>";
        echo $sOutput;

        exit();
    }

    public function sendJs(){

    $sOutput = "
    var images = [
        ";
    /**
     * @var $oFile ofgImage
     */
    foreach($this->aFiles as $oFile){
        $sOutput.= "
        {
            rsz:{
                file:'".$oFile->sResizedFile()."',
                x:".$oFile->resizedWidth().",
                y:".$oFile->resizedHeight()."
                },
            crop:{
                file:'".$oFile->sCroppedFile()."',
                x:".$oFile->croppedWidth().",
                y:".$oFile->croppedHeight()."
                },
            file:'".$oFile->sFile()."',
            x:".$oFile->width().",
            y:".$oFile->height().",
            size:'".$oFile->humanFileSize()."'
        },
        ";
    }
        $sOutput.= "
        ];
        console.log(images);";

        $sOutput.='
var style = "rows";

var col = document.getElementById("col");
var iMaxWidth = col.offsetWidth ;
var iPadding = parseInt(window.getComputedStyle(col,null).getPropertyValue("padding-left"),10)+1;
iMaxWidth -= 2*iPadding;
iMaxWidth-=20; // -20px for scrollbar...

var tpl = {
    row:{
        tpl: "<div class=\'row\'>@IMGS</div>",
        img:"<img src=\'@SRC\' class=\'thumb\' height=\'@HEIGHT\'>"
    },
    list:{
        tpl:\'<ul class="collection">@LIST</ul>\',
        listitem:
        \'<li class="collection-item avatar">\' +
            \'<a href="@BIGSRC">\'+
                \'<img src="@SRC" class="circle medium">\'+
                \'<p>@TITLE</p>\'+
                \'<p>@INFO</p>\'+
            \'</a>\'+
        \'</li>\'
    },
    grid:{
        tpl:\'<div class="col s12">@IMAGES</div>\',
        img:"<img src=\'@SRC\' class=\'nojs\'>"
    }
};

function calculateRowWidth(){
    iMaxWidth = col.offsetWidth ;
    var iPadding = parseInt(window.getComputedStyle(col,null).getPropertyValue("padding-left"),10)+1;
    iMaxWidth -= 2*iPadding;
    iMaxWidth-=5; // -20px for scrollbar

    console.log("iMaxWidth: "+iMaxWidth);
}

function createView(){
    calculateRowWidth();
    switch (style){
        default:
        case "rows":
            createImageRows();
            break;
        case "list":
            createList();
            break;
        case "grid":
            createImageGrid();
            break;
    }
}

function createImageGrid(){
    var row = [];
    var iRowWidth = 0;


    col.innerHTML = "";

    // add images to a row until width of all images is bigger than row
    for(i=0;i<images.length;i++){
        row.push(images[i].crop);
        iRowWidth += images[i].crop.x;

        if(iRowWidth >= iMaxWidth){
            createRow(row);
            row = [];
            iRowWidth = 0;
        }
    }
    if(row.length>0)
        createRow(row)
}

function createImageRows(){
    var row = [];
    var iRowWidth = 0;


    col.innerHTML = "";

    // add images to a row until width of all images is bigger than row
    for(i=0;i<images.length;i++){
        row.push(images[i].rsz);
        iRowWidth += images[i].rsz.x;

        if(iRowWidth >= iMaxWidth){
            createRow(row);
            row = [];
            iRowWidth = 0;
        }
    }
    if(row.length>0)
        createRow(row)
}

function createRow(RowImages){
    var imgHtml = "";

    var iImagesLength = 0;
    for(var i=0;i<RowImages.length;i++){
        iImagesLength += RowImages[i].x;
    }

    for(i=0;i<RowImages.length;i++){
        if(iImagesLength < iMaxWidth)
            RowImages[i].newY = RowImages[i].y;
        else
            RowImages[i].newY = (RowImages[i].y * (iMaxWidth/iImagesLength)).toFixed(1)+2;
    }

    for(i=0;i<RowImages.length;i++){
        imgHtml += tpl.row.img  .replace("@SRC",RowImages[i].file)
                            .replace("@HEIGHT",RowImages[i].newY+"px");
    }

    col.innerHTML += tpl.row.tpl.replace("@IMGS",imgHtml);
}

function createList(){
    col.innerHTML = "";

    var sListHtml = "";

    for (var i=0;i<images.length;i++){
        sListHtml += tpl.list.listitem.replace("@SRC",images[i].crop.file)
            .replace("@TITLE",images[i].file)
            .replace("@INFO",images[i].x + "x"+images[i].y+"px")
            .replace("@BIGSRC",images[i].file);
    }

    col.innerHTML = tpl.list.tpl.replace("@LIST",sListHtml);
}

window.addEventListener(\'resize\',function(){
    console.log("resize");
    createView();
},true);

window.addEventListener(\'load\',function(){
    console.log("load");
    createView();
},true);

function iconMenu(e){

    e.preventDefault();
    var elIcon = document.getElementById("viewIcon");

    var sClassname = e.srcElement.className;
    if (sClassname.match(/list/)) {
        style = "list";
        document.cookie = "view=list";
        elIcon.innerHTML = "<li><a href=\"?view=grid\" class=\'icon icon-rows\' title=\'view as image-grid\'></a></li>";
    } else if (sClassname.match(/rows/)){
        style = "rows";
        elIcon.innerHTML = "<li><a href=\"?view=grid\" class=\'icon icon-grid\' title=\'view as grid\'></a></li>";
    }else if (sClassname.match(/grid/)){
        style="grid";
        document.cookie = "view=grid";
        elIcon.innerHTML = "<li><a href=\"?view=list\" class=\'icon icon-list\' title=\'view as list\'></a></li>";
    }

    addEventlistener();
    createView();
}

function addEventlistener(){
    var menuButtons = document.querySelectorAll("a.icon");
    for(var i=0;i<menuButtons.length;i++){
        menuButtons[i].addEventListener("click",function(e){
            iconMenu(e);
        })
    }
}

addEventlistener();

var jsonly = document.querySelectorAll(".js-only");
for(i=0;i<jsonly.length;i++)
    jsonly[i].className = jsonly[i].className.replace(/\bjs-only\b/,\'\');
        ';

        header('Content-Type: application/javascript');

        echo $sOutput;
        exit();
    }

}


// example how to set the dir for thumbs, default is .ofgThumbs
// ofg::setThumbDir(".ofgThumbs");

// create gallery
new ofg();

