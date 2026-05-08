var response = document.querySelector("#response");
var updateForm = document.getElementById("updateForm");
let currentAudio = null;
function save(prompt_id) {
    var prompt_title = document.querySelector('#prompt_title').value;
    var text = document.querySelector('#text').value;
    var prepare_time = document.querySelector('#prepare_time').value;
    var response_time = document.querySelector('#response_time').value;

    var transcription = document.querySelector('#transcriptionReq').checked ? 1 : 0;
    var read_prompt = document.querySelector('#readPromptAloud').checked ? 1 : 0;

    var fd = new FormData();
    fd.append('title', prompt_title);
    fd.append('text', text);
    fd.append('prepare_time', prepare_time);
    fd.append('response_time', response_time);
    fd.append('transcription', transcription);
    fd.append('read_prompt', read_prompt);
    fd.append('prompt_id', prompt_id);

   


    var xmlHttp = new XMLHttpRequest();
    xmlHttp.onreadystatechange = function () {
        if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
            response.classList.remove("alert", "alert-danger");
            response.classList.add("alert", "alert-success");
            response.innerHTML = xmlHttp.responseText;
        } else if (xmlHttp.readyState == 4) {
            response.classList.remove("alert", "alert-success");
            response.classList.add("alert", "alert-danger");
            response.innerHTML = "Prompt could not be saved. Please try again.";
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

function copyPromptLink() {
    var copyButton = document.querySelector("#copyPromptLink");
    if (!copyButton || !copyButton.dataset.promptUrl) {
        return;
    }

    if (!navigator.clipboard) {
        copyButton.innerHTML = "Copy Unavailable";
        return;
    }

    navigator.clipboard.writeText(copyButton.dataset.promptUrl).then(function () {
        var originalText = copyButton.innerHTML;
        copyButton.innerHTML = "Copied";
        copyButton.classList.remove("btn-outline-primary");
        copyButton.classList.add("btn-primary");
        setTimeout(function () {
            copyButton.innerHTML = originalText;
            copyButton.classList.remove("btn-primary");
            copyButton.classList.add("btn-outline-primary");
        }, 2000);
    }, function () {
        copyButton.innerHTML = "Copy Failed";
    });
}

document.querySelectorAll('.audio-controls').forEach(audio => {
    audio.addEventListener('play', playAudio);
});
