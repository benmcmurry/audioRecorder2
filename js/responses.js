var response = document.querySelector("#response");
var updateForm = document.getElementById("updateForm");
let currentAudio = null;
function save(prompt_id) {
   
    console.log(prompt_title);
    var prompt_title = document.querySelector('#prompt_title').value;
    var text = document.querySelector('#text').value;
    var prepare_time = document.querySelector('#prepare_time').value;
    var response_time = document.querySelector('#response_time').value;

    var transcription = document.querySelector('#transcriptionReq').checked;

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
function playAudio(event) {
    if (currentAudio && currentAudio !==event.target) {
        console.log("Stopping current audio:", currentAudio.src);

        currentAudio.pause();
    }
    currentAudio = event.target;
    console.log("Playing new audio:", currentAudio.src);

}

document.querySelectorAll('.audio-controls').forEach(audio => {
    audio.addEventListener('play', playAudio);
});
