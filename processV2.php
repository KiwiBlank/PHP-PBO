<?php
require_once("bytebuffer/Stream.php");

$Process = new Process();

class Process {

  private $_OUTPUTFOLDER;
  private $_FILEITERATOR;
  private $_MAXFILEITERATOR;
  private $_USERRELATIVEPATH;
  private $_ENABLECOMPRESSION;
  private $_TIMESTAMP;
  private $_FINALOUTPUTNAME;
  private $_UPLOADPATH;
  private $_FILEARRAY;

  // Constructor is called when class is initialized.
  function __construct() {
    // What folder to place uplaoded files. Example: C:\xampp\htdocs\uploads
    $this->_OUTPUTFOLDER = "uploads";
    // Files are sent in a for loop and this value represents the current file being sent in the loop.
    $this->_FILEITERATOR = intval($_POST["clength"]);
    // The final file being sent is indicated by being the same as the max iterator.
    $this->_MAXFILEITERATOR = intval($_POST["mlength"]);
    // The relative path to the folder the user has picked.
    $this->_USERRELATIVEPATH = dirname($_POST["path"]);
    // Boolean indicating whether compression is enabled.
    if (isset($_POST["compress"])) {
      $this->_ENABLECOMPRESSION = $_POST["compress"];
    } else {
      $this->_ENABLECOMPRESSION = false;
    }
    // Timestamp which indicates what time files are sent by what user. This is really bad as if it takes longer than 1 minute to upload, or the same user sends the same file within a minute.
    $this->_TIMESTAMP = date("zHi");
    // What the final .pbo will be called according to the folder name sent by user AND the current timestamp. Also bad if timestamps don't line up for multiple files.
    $this->_FINALOUTPUTNAME = "{$this->_TIMESTAMP}{$this->_USERRELATIVEPATH}";

    $this->moveFiles();
	}

  function moveFiles() {
    $filesArray = $_FILES["file"];

    $upload_dir = getcwd() . DIRECTORY_SEPARATOR . $this->_OUTPUTFOLDER . DIRECTORY_SEPARATOR . $this->_FINALOUTPUTNAME;

    $this->_UPLOADPATH = $upload_dir . DIRECTORY_SEPARATOR . basename($filesArray['name']);

    if (!is_dir($upload_dir))
    {
        // Might have issues in linux where you require access to make directories / files.
        mkdir($upload_dir, 0700, true);
    }
    move_uploaded_file($filesArray['tmp_name'], $this->_UPLOADPATH);

    // If this is the final file being sent, which starts the packing process.
    if ($this->_FILEITERATOR >= $this->_MAXFILEITERATOR) {
      $this->iterateFiles();
    }

  }

  function iterateFiles() {
    $explodeOutputName = explode('/', $this->_FINALOUTPUTNAME);
    $directoryFinal = $explodeOutputName[0];

    $folderRII = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$this->_OUTPUTFOLDER/$directoryFinal"));

    // Compression is enabled.
    if ($this->_ENABLECOMPRESSION) {
      $this->packPBOCompression($folderRII, $directoryFinal);
    } else {
      $this->packPBO($folderRII, $directoryFinal);
    }

  }

  // PBO will be packed with compression
  function packPBOCompression($folderRII, $directoryFinal) {

    $mainStream = \ByteBuffer\Stream::factory('', []);
    // Compression uses two streams to do the compression, this is until i think of a better way to do this.
    $compressionStream = \ByteBuffer\Stream::factory('', []);

    $mainStream->write('Overpoch');
    $mainStream->writeNull(1); // This might be wrong, as it should be filename according to wiki
    $mainStream->write('srpC'); // 0x43707273 (https://community.bistudio.com/wiki/PBO_File_Format#PBO_Header_Entry)

    $this->streamHandler($mainStream, $folderRII, true);

    // Write the file content from array.
    foreach ($this->_FILEARRAY as $fileContent) {
      $compressionStream->write($fileContent);
    }
    // Has to save a file for the lzss to work. I would just send it as a string if I could...
    $compressionStream->save("{$this->_OUTPUTFOLDER}/{$directoryFinal}.COMPRESS");

    // Will improve.
    $compressInput = getcwd();
    $compressInput .= DIRECTORY_SEPARATOR . $this->_OUTPUTFOLDER . DIRECTORY_SEPARATOR . "{$directoryFinal}.COMPRESS";

    $compressOutput = getcwd();
    $compressOutput .= DIRECTORY_SEPARATOR . $this->_OUTPUTFOLDER . DIRECTORY_SEPARATOR . "{$directoryFinal}.COMPRESSOUTPUT";

    // Here comes the magic, see (https://github.com/MichaelDipperstein/lzss) for the program. Note: Has to be added to enviroment variable.
    exec("lzss -c -i $compressInput -o $compressOutput");

    // Now read the new compressed file.
    $readCompression = file_get_contents($compressOutput);

    // Then finally append it to the mainStream
    $mainStream->write($readCompression);

    $this->sendPacked($mainStream, $directoryFinal);

    // Remove the compression files as they are not sent to user or used anymore.
    unlink($compressInput);
    unlink($compressOutput);

  }

  // PBO will be packed without compression
  function packPBO($folderRII, $directoryFinal) {
    $mainStream = \ByteBuffer\Stream::factory('', []);

    $mainStream->writeNull(1); // This might be wrong, as it might be filename according to wiki
    $mainStream->write('sreV'); // 0x56657273 (https://community.bistudio.com/wiki/PBO_File_Format#PBO_Header_Entry)

    $this->streamHandler($mainStream, $folderRII, false);

    // Write the file content from array.
    foreach ($this->_FILEARRAY as $fileContent) {
      $mainStream->write($fileContent);
    }

    $this->sendPacked($mainStream, $directoryFinal);

  }

  // Function to save and redirect user to the url where the final .pbo is.
  function sendPacked($mainStream, $directoryFinal) {

    $mainStream->save("{$this->_OUTPUTFOLDER}/{$directoryFinal}.pbo");
    $fileDownload = "$this->_OUTPUTFOLDER" . DIRECTORY_SEPARATOR . "$directoryFinal.pbo";

    echo $fileDownload;

    // Header 202 is to indicate that the file is going to be sent.
    header(http_response_code(202));

  }

  function streamHandler($mainStream, $folderRII, $compress) {

    // Start of the header.
    for ($x = 0; $x <= 16; $x++) {
      $mainStream->writeNull(1);
    }

    $fileArray = array();

    foreach ($folderRII as $file) {

        $fileSize = filesize($file);
        $fileLastEdit = filemtime($file);
        $fileName = $file->getPathname();
        $fileNameTrimmed = trim(substr($fileName, strpos($fileName, '\\') + 1));

        if ($file->isDir()){
            continue;
        }

        array_push($fileArray,file_get_contents($file));

        $mainStream->write($fileNameTrimmed); // Asciiz filename;
        $mainStream->writeNull(1);
        $mainStream->writeBytes([0x00000000]); // char[4] MimeType;
        $mainStream->writeBytes([$fileSize]); // ulong OriginalSize;

        $mainStream->writeBytes([0]); // ulong Offset; (0)
        $mainStream->writeBytes([$fileLastEdit]); // ulong TimeStamp; (Could be 0)
        $mainStream->writeBytes([0]); // ulong DataSize; $fileSize

    }

    // End the header, then file content begins.
    for ($x = 0; $x <= 20; $x++) {
      $mainStream->writeNull(1);
    }

    // Store the file array as a variable in the class so it can be used by compression & non compression
    $this->_FILEARRAY = $fileArray;


  }

}


?>
