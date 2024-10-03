"use strict";
var archiveToggleButton = document.querySelector("#archiveToggleButton");
var showArchivedPrompts = false;
var archived = document.getElementsByClassName("archived");
var response = document.querySelector("#response");
const prompts = [];
var promptCount = 0;
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
  fd.append("prompt_id", prompt_id);
  fd.append("archiveStatus", archiveStatus);

  var xmlHttp = new XMLHttpRequest();
  console.log(xmlHttp);
  xmlHttp.onreadystatechange = function () {
    if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
      response.innerHTML = xmlHttp.responseText;
    } else {
      console.log("failed");
    }
  };
  xmlHttp.open("post", "../phpScripts/archiveToggle.php");
  xmlHttp.send(fd);
}

function createPrompt() {
  console.log("CreatePrompt Function Activites");
  var xmlHttp = new XMLHttpRequest();
  xmlHttp.onreadystatechange = function () {
    if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
      window.location.href = xmlHttp.responseText;
    } else {
      console.log("failed");
    }
  };
  xmlHttp.open("post", "../phpScripts/createPrompt.php");
  xmlHttp.send();
}

function copyLink(prompt_id, server) {
  var copyButton = document.querySelector(`#link-${CSS.escape(prompt_id)}`);

  var copyIcon = document.querySelector(
    `#link-${CSS.escape(prompt_id)}`
  ).childNodes;
  console.log(copyIcon[0]);
  copyIcon[0].classList.toggle("bi-clipboard");
  copyIcon[0].classList.toggle("bi-clipboard-check");
  copyButton.classList.toggle("btn-outline-primary");
  copyButton.classList.toggle("btn-primary");

  var link = server + "?prompt_id=" + prompt_id;
  navigator.clipboard.writeText(link).then(
    function () {
      console.log("copied: " + link);
    },
    function () {
      console.log("copy failed");
    }
  );
  setTimeout(function () {
    copyIcon[0].classList.toggle("bi-clipboard");
    copyIcon[0].classList.toggle("bi-clipboard-check");
    copyButton.classList.toggle("btn-outline-primary");
    copyButton.classList.toggle("btn-primary");
  }, 3000);
}

function selectMultiple(prompt) {
 
  if (prompts.includes(prompt)) {
    // index = prompts.indexOf(prompt);
    prompts.splice(prompts.indexOf(prompt), 1);
    promptCount = promptCount - 1;
  } else {
    if (promptCount == 6) {
      alert("Sorry, you can only look at 6 at a time.");
      document.getElementById("flexCheckDefault-" + prompt).checked = false;
      return;
    } else {
      prompts.push(prompt);
      promptCount = promptCount + 1;
    }
  }
}

function multipleViewer() {
  var promptsURL = prompts.join();
  window.location.href = "../responses/responses.php?prompts=" + promptsURL;
}
