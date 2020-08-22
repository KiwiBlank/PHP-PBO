let folderPicker = document.getElementById('picker');

folderPicker.addEventListener('change', e => {
  var compress = document.getElementById("compress").checked;
  if (compress) {

    for (var i = 0; i < folderPicker.files.length; i++) {
        var file = folderPicker.files[i];
        var mlength = folderPicker.files.length - 1;
        var clength = i;
        var compress = true;
        sendFile(file, file.webkitRelativePath, mlength, clength, compress);
    }

  } else {
    for (var i = 0; i < folderPicker.files.length; i++) {
        var file = folderPicker.files[i];
        var mlength = folderPicker.files.length - 1;
        var clength = i;
        var compress = false;
        sendFile(file, file.webkitRelativePath, mlength, clength, compress);
    }
  }


});


sendFile = function(file, path, mlength, clength, compress) {

    var item = document.createElement('li');
    var formData = new FormData();
    var request = new XMLHttpRequest();

    request.responseType = 'text';

    request.onload = function () {
        if (request.readyState === request.DONE) {
            if (request.status === 202) {
              console.log(request.responseText);
              window.location.href = request.responseText;
            }
        }
      }

    formData.set('file', file);
    formData.set('path', path);
    formData.set('mlength', mlength);
    formData.set('clength', clength);

    if (compress) {
      formData.set('compress', compress);
    }

    request.open("POST", 'processV2.php', true);
    request.send(formData);

};
