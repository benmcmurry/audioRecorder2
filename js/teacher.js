'use strict'
var archiveToggleButton = document.querySelector("#archiveToggleButton");
var showArchivedPrompts = false;
var archived = document.getElementsByClassName("archived");
var abc = document.querySelector("#abc");

var promptInQuestion;
var icon;

function archiveToggle() {
    if (showArchivedPrompts) {
        archiveToggleButton.innerHTML = "Show Archived Prompts";
        showArchivedPrompts = false;
        var i;
        for (i = 0; i < archived.length; i++) {
            archived[i].classList.toggle("d-none");
        }
    } else {
        archiveToggleButton.innerHTML = "Hide Archived Prompts";
        showArchivedPrompts = true;
        var i;
        for (i = 0; i < archived.length; i++) {
            archived[i].classList.toggle("d-none");
        }
    }
}

function archive(prompt_id, archiveStatus) {
    promptInQuestion = document.querySelector(`#${CSS.escape(prompt_id)}`);
    icon = document.querySelector(`#icon-${CSS.escape(prompt_id)}`);
    promptInQuestion.classList.toggle("current");
    promptInQuestion.classList.toggle("archived");

    icon.classList.toggle("bi-archive-fill");
    icon.classList.toggle("bi-archive");
    if (!showArchivedPrompts) {
        promptInQuestion.classList.toggle("d-none");
    }

    var fd = new FormData();
    fd.append('prompt_id', prompt_id);
    fd.append('archiveStatus', archiveStatus);

    var xmlHttp = new XMLHttpRequest();
    console.log(xmlHttp);
    xmlHttp.onreadystatechange = function () {
        if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
            abc.innerHTML = xmlHttp.responseText;
        } else {
            console.log("failed");
        }
    }
    xmlHttp.open("post", "../phpScripts/archiveToggle.php");
    xmlHttp.send(fd);



}

function createPrompt(){
    console.log("CreatePrompt Function Activites");
}

// function copyLink(prompt_id, server) {
//     var link = document.querySelector(`#link-${CSS.escape(prompt_id)}`);
//     var linkText = link.textContent;
//     copyText(linkText);
//     // alert("Link Copied: " + linkText);

//     // document.execCommand("copy");
// //   copyText.select();
// //   document.execCommand("copy");
// }

// function copyText(text) {
//     navigator.clipboard.writeText(text);
//     alert(text);
    
//   }

