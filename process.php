
<?php
require_once("bytebuffer/Stream.php");

//Set what folder to place files
$folderOutput = "uploads";


$currentIteration = intval($_POST["clength"]);
$maxIteration = intval($_POST["mlength"]);

$dirPath = dirname($_POST["path"]);

$timestamp = date("zHi");
$directoryTimestamp = "{$timestamp}{$dirPath}";


processFile($directoryTimestamp, $_FILES["file"], $folderOutput);


function processFile($path, $files, $folderOutput)
{

    $upload_dir = getcwd() . DIRECTORY_SEPARATOR . $folderOutput . DIRECTORY_SEPARATOR . $path;

    $upload_path = $upload_dir . DIRECTORY_SEPARATOR . basename($files['name']);

    if (!is_dir($upload_dir))
    {
        mkdir($upload_dir, 0700, true);
    }
    move_uploaded_file($files['tmp_name'], $upload_path);
}



// If this is the last file being sent to process.php
if ($currentIteration == $maxIteration) {

  $explode = explode('/', $directoryTimestamp);
  $directoryFinal = $explode[0];

  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$folderOutput}/{$directoryFinal}"));

  $stream = \ByteBuffer\Stream::factory('', []);

  $stream->writeNull(1);
  $stream->write('sreV');

// TODO Add support for PBOPrefix
  for ($x = 0; $x <= 16; $x++) {
    $stream->writeNull(1);
  }

  $fileArray = array();

    foreach ($rii as $file) {

        $fileSize = filesize($file);
        $fileLastEdit = filemtime($file);
        $fileName = $file->getPathname();
        $fileNameTrimmed = trim(substr($fileName, strpos($fileName, '\\') + 1));

        if ($file->isDir()){
            continue;
        }

        array_push($fileArray,file_get_contents($file));

        $stream->write($fileNameTrimmed);
        $stream->writeNull(1);
        $stream->writeBytes([0x00000000]);
        $stream->writeBytes([$fileSize]);

        $stream->writeBytes([0]); // Reserved
        $stream->writeBytes([$fileLastEdit]);
        $stream->writeBytes([$fileSize]);

    }
  for ($x = 0; $x <= 20; $x++) {
    $stream->writeNull(1);
  }

  foreach ($fileArray as $fileContent) {
    $stream->write($fileContent);
  }
  $stream->save("{$folderOutput}/{$directoryFinal}.pbo");
  $fileDownload = "http://$_SERVER[HTTP_HOST]/{$folderOutput}/{$directoryFinal}.pbo";

  echo $fileDownload;
  header(http_response_code(202));


}


?>
