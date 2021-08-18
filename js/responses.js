var response = document.querySelector("#response");

function save(prompt_id) {
    var prompt_title = document.querySelector('#prompt_title').innerHTML;
    var text = document.querySelector('#text').innerHTML;
    var prepare_time = document.querySelector('#prepare_time').innerHTML;
    var response_time = document.querySelector('#response_time').innerHTML;

    var transcription = document.querySelector('input[name="transcriptionReq"]:checked').value;

    var fd = new FormData();
    fd.append('title', prompt_title);
    fd.append('text', text);
    fd.append('prepare_time', prepare_time);
    fd.append('response_time', response_time);
    fd.append('transcription', transcription);
    fd.append('prompt_id', prompt_id);

    var xmlHttp = new XMLHttpRequest();
    xmlHttp.onreadystatechange = function () {
        if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
            response.innerHTML = xmlHttp.responseText;
        }
    }
    xmlHttp.open("post", "../phpScripts/updatePrompt.php");
    xmlHttp.send(fd);

    console.log(fd);
    for (var pair of fd.entries()) {
        console.log(pair[0] + ', ' + pair[1]);
    }
}