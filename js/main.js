"use strict";
var constraints = {
  audio: true,
  video: false,
};
var safari;
if (
  navigator.userAgent.indexOf("Safari") != -1 &&
  navigator.userAgent.indexOf("Chrome") == -1
) {
  safari = true;
}
var liveAudioElement = document.querySelector("#live");
var playbackAudioElement = document.querySelector("#playback");
liveAudioElement.controls = false;
var mediaRecorder;
var chunks = [];
var count = 0;
var localStream = null;
var soundMeter = null; //not in use right now
var containerType = "audio/webm";
var extension = "webm";
var analyser; //variable for visualizing sound
var scriptProcessor; //variable for visualizing sound
var input; //variable for visualizing sound
var recordingStatus;
var testButton = document.querySelector("#testButton");
var buttons = document.querySelector("#buttons");
var prompt = document.querySelector("#prompt");
var downloadLink = document.querySelector("#downloadLink");
var prepareAndRecord = document.querySelector("#prepareAndRecord");
var timerContainer = document.querySelector("#timerContainer");
var timeOrRecord = document.querySelector("#timeOrRecord");
var alreadyDoneBox = document.querySelector("#alreadyDoneBox");
var alreadyAnswered = document.querySelector("#alreadyAnswered");
var transcriptionBox = document.querySelector("#transcriptionBox");
var visualizer = document.querySelector("#visualizer");
var reviewRecording = document.querySelector("#reviewRecording");
var repeatRecording = document.querySelector("#repeatRecording");
var recordTime = 5000;
var response = document.querySelector("#response");
var transcriptionRow = document.querySelector("#transcriptionRow");
var transcription;
var i = 0;


if ((transcriptionRequired = 1)) {
  document
    .querySelector("#transcriptionBox")
    .addEventListener("keyup", function (e) {
      if (e.keyCode == 32 || e.keyCode == 190 || e.keyCode == 13) {
        saveTranscription(netid, prompt_id);
      }
    });
}
document
  .querySelector("#alreadyAnswered")
  .addEventListener("click", function () {
    i = i + 1;
    if (i === 3) {
      repeatRecording.classList.remove("d-none");
      repeatPassword.focus();
    }
  });

document
  .querySelector("#repeatPassword")
  .addEventListener("keydown", function (e) {
    if (
      e.keyCode == 13 &&
      document.getElementById("repeatPassword").value == "repeat"
    ) {
      var fd = new FormData();
      fd.append("prompt_id", prompt_id);
      fd.append("netid", netid);
      var xmlHttp = new XMLHttpRequest();
      xmlHttp.onreadystatechange = function () {
        if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
          repeatRecording.innerHTML = xmlHttp.responseText;
        }
      };
      xmlHttp.open("post", "phpScripts/removeDBentry.php");
      xmlHttp.send(fd);
    }
  });

if (alreadyDone) {
  buttons.classList.add("d-none");
  visualizer.classList.add("d-none");
  alreadyDoneBox.classList.remove("d-none");
  reviewRecording.src = reviewSource;
  reviewRecording.type = reviewSourceType;
}

//this initializes (is that the right word?) the mediaRecorder
if (!navigator.mediaDevices.getUserMedia) {
  alert(
    "navigator.mediaDevices.getUserMedia not supported on your browser, use the latest version of Firefox or Chrome"
  );
} else {
  if (window.MediaRecorder == undefined) {
    alert(
      "MediaRecorder not supported on your browser, use the latest version of Firefox or Chrome"
    );
  } else {
    navigator.mediaDevices
      .getUserMedia(constraints)
      .then(function (stream) {
        localStream = stream;

        localStream.getTracks().forEach(function (track) {
          if (track.kind == "audio") {
            track.onended = function (event) {
              console.log(
                "audio track.onended Audio track.readyState=" +
                  track.readyState +
                  ", track.muted=" +
                  track.muted
              );
            };
          }
          if (track.kind == "video") {
            track.onended = function (event) {
              console.log(
                "video track.onended Audio track.readyState=" +
                  track.readyState +
                  ", track.muted=" +
                  track.muted
              );
            };
          }
        });

        liveAudioElement.srcObject = localStream;
        liveAudioElement.play();

        try {
          window.AudioContext =
            window.AudioContext || window.webkitAudioContext;
          window.audioContext = new AudioContext();
          visualizationOfSound(stream);
        } catch (e) {
          console.log("Web Audio API not supported." + e);
        }
      })
      .catch(function (err) {
        /* handle the error */
        console.log("navigator.getUserMedia error: " + err);
      });
  }
}

//this function starts when test Microphone in pressed
function testStartRecording() {
  testButton.innerHTML = "Please Speak";
  testButton.classList.remove("btn-success");
  testButton.classList.add("btn-danger");
  testButton.classList.add("oscillate");

  record("microphoneTest");
}

//this function starts recording when the Begin button is pressed
function startRecording() {
  buttons.classList.add("d-none");
  prompt.classList.remove("d-none");

  timer_container.classList.remove("d-none");
  speakPrompt(promptText, prepare_time, response_time);
  setTimeout(function() {timer(prepare_time, "Prepare");
  playbackAudioElement.controls = false;}, promptText.length* 100);

  

  setTimeout(function () {
    timer(response_time, "Recording");
    record("recording");
  }, prepare_time * 1000 + 1000 + promptText.length * 100);
}

//
async function speakPrompt(promptText, prepare_time, response_time) {
  var msg = new SpeechSynthesisUtterance();
  msg.lang = "en";
  msg.text =
    promptText +
    " You have" +
    prepare_time +
    " seconds to prepare and " +
    response_time +
    " seconds to respond.";
  window.speechSynthesis.speak(msg);

}

//this function does the actual capturing of the audio.
function record(typeOfRecording) {
  if (localStream == null) {
    alert("Could not get local stream from mic/camera");
  } else {
    chunks = [];

    /* use the stream */
    console.log("Start recording...");
    // this will check to see which codec to use
    if (MediaRecorder.isTypeSupported("audio/webm")) {
      containerType = "audio/webm";
      var options = {
        mimeType: "audio/webm",
      };
      extension = "webm";
    } else if (MediaRecorder.isTypeSupported("audio/mp4")) {
      //Safari 14.0.2 has an EXPERIMENTAL version of MediaRecorder enabled by default
      containerType = "audio/mp4";
      var options = {
        mimeType: "audio/mp4",
      };
      extension = "mp4";
    }

    mediaRecorder = new MediaRecorder(localStream, options);

    mediaRecorder.ondataavailable = function (e) {
      console.log("mediaRecorder.ondataavailable, e.data.size=" + e.data.size);
      if (e.data && e.data.size > 0) {
        chunks.push(e.data);
      }
    };

    mediaRecorder.onerror = function (e) {
      console.log("mediaRecorder.onerror: " + e);
    };

    mediaRecorder.onstart = function () {
      console.log(
        "mediaRecorder.onstart, mediaRecorder.state = " + mediaRecorder.state
      );

      localStream.getTracks().forEach(function (track) {
        if (track.kind == "audio") {
          console.log(
            "onstart - Audio track.readyState=" +
              track.readyState +
              ", track.muted=" +
              track.muted
          );
        }
        if (track.kind == "video") {
          console.log(
            "onstart - Video track.readyState=" +
              track.readyState +
              ", track.muted=" +
              track.muted
          );
        }
      });
    };

    mediaRecorder.onstop = function () {
      console.log(
        "mediaRecorder.onstop, mediaRecorder.state = " + mediaRecorder.state
      );
      if (typeOfRecording === "microphoneTest") {
        testButton.innerHTML = "Re-Test Microphone";
        testButton.classList.remove("btn-danger");
        testButton.classList.add("btn-success");
      }
      var recording = new Blob(chunks, {
        type: mediaRecorder.mimeType,
      });
      if (typeOfRecording === "microphoneTest") {
        playbackAudioElement.src = URL.createObjectURL(recording);
        if (safari == true) {
          playbackAudioElement.controls = true;
        }
      } else {
        reviewRecording.src = URL.createObjectURL(recording);
      }
      console.log(playbackAudioElement.src);

      if (typeOfRecording === "recording") {
        reviewRecording.controls = true;
      } else {
        if (safari == true) {
          reviewRecording.controls = true;
        } else {
          reviewRecording.play();
        }
      }
      var d = Date.now();
      var name = "prompt_" + prompt_id + "_" + netid + "-" + d + ".";

      console.log(name);

      if (typeOfRecording === "recording") {
        uploadRecording(recording, name);
      } else {
        testButton.classList.remove("oscillate");
      }
    };

    mediaRecorder.onpause = function () {
      console.log(
        "mediaRecorder.onpause, mediaRecorder.state = " + mediaRecorder.state
      );
    };

    mediaRecorder.onresume = function () {
      console.log(
        "mediaRecorder.onresume, mediaRecorder.state = " + mediaRecorder.state
      );
    };

    mediaRecorder.onwarning = function (e) {
      console.log("mediaRecorder.onwarning: " + e);
    };

    // pauseResBtn.textContent = "Pause";
    if (typeOfRecording === "recording") {
      recordTime = response_time * 1000 + 500;
      timeOrRecord.src = "images/record.jpg";
    } else {
    }
    mediaRecorder.start(1000);

    localStream.getTracks().forEach(function (track) {
      console.log(track.kind + ":" + JSON.stringify(track.getSettings()));
      console.log(track.getSettings());
    });
    (function () {
      setTimeout(function () {
        console.log("stop recording");
        mediaRecorder.stop();
      }, recordTime);
    })();
  }
}

function uploadRecording(blob, name) {
  prompt.classList.add("d-none");
  console.log(blob);
  var fd = new FormData();
  fd.append("name", name);
  fd.append("extension", extension);
  fd.append("myBlob", blob);
  fd.append("prompt_id", prompt_id);
  fd.append("netid", netid);
  fd.append("transcription", transcription);
  var xmlHttp = new XMLHttpRequest();
  xmlHttp.onreadystatechange = function () {
    if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
      alreadyAnswered.innerHTML = xmlHttp.responseText;
    }
  };
  xmlHttp.open("post", "upload.php");
  xmlHttp.send(fd);
  visualizer.classList.add("d-none");
  prepareAndRecord.classList.add("d-none");
  alreadyDone = true;
  alreadyDoneBox.classList.remove("d-none");
}

function saveTranscription(netid, prompt_id) {
  transcription = transcriptionBox.value;
  var fd = new FormData();
  fd.append("netid", netid);
  fd.append("prompt_id", prompt_id);
  fd.append("transcription", transcription);
  var xmlHttp = new XMLHttpRequest();
  xmlHttp.onreadystatechange = function () {
    if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
      response.innerHTML = xmlHttp.responseText;
    }
  };
  xmlHttp.open("post", "phpScripts/saveTranscription.php");
  xmlHttp.send(fd);
}

//this function reads the prompt

function read(prompt) {
  var msg = new SpeechSynthesisUtterance();
  msg.text = prompt;
  window.speechSynthesis.speak(msg);
}
//this function runs the timers
function timer(time, timerType) {
  (function move() {
    // this keeps the counter running in a loop based on changed information
    if (time > -1) {
      document.getElementById("timer").innerHTML = timerType + " " + time + "s";
      setTimeout(move, 1000);
      time = time - 1;
    }
  })();
}

//this gives us the sound visualization
function visualizationOfSound(stream) {
  analyser = window.audioContext.createAnalyser();
  scriptProcessor = window.audioContext.createScriptProcessor(2048, 1, 1);
  analyser.smoothingTimeConstant = 0.3;
  analyser.fftSize = 1024;
  input = window.audioContext.createMediaStreamSource(stream);
  input.connect(analyser);
  analyser.connect(scriptProcessor);
  scriptProcessor.connect(window.audioContext.destination);
  scriptProcessor.onaudioprocess = processInput;
}

//this processes the incoming sound for the visualization
function processInput() {
  var recordingStatus = true;
  if (recordingStatus) {
    var soundArray = new Uint8Array(analyser.frequencyBinCount);
    var volume;
    analyser.getByteFrequencyData(soundArray);
    length = soundArray.length;
    let values = 0;
    let i = 0;
    for (; i < length; i++) {
      values += soundArray[i];
    }
    volume = (values / length) * 3 + 10;
    (function () {
      var elements = document.getElementsByClassName("volbox");
      for (var i = 0; i < elements.length; i++) {
        elements[i].style.backgroundColor = "white";
      }
    })();

    if (volume > 320) {
      (function () {
        document.getElementById("volbox-12").style.backgroundColor = "red";
      })();
    }
    if (volume > 304) {
      (function () {
        document.getElementById("volbox-11").style.backgroundColor = "yellow";
      })();
    }
    if (volume > 288) {
      (function () {
        document.getElementById("volbox-10").style.backgroundColor = "yellow";
      })();
    }
    if (volume > 240) {
      (function () {
        document.getElementById("volbox-9").style.backgroundColor = "yellow";
      })();
    }
    if (volume > 224) {
      (function () {
        document.getElementById("volbox-8").style.backgroundColor = "yellow";
      })();
    }
    if (volume > 192) {
      (function () {
        document.getElementById("volbox-7").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 176) {
      (function () {
        document.getElementById("volbox-6").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 144) {
      (function () {
        document.getElementById("volbox-5").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 128) {
      (function () {
        document.getElementById("volbox-4").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 96) {
      (function () {
        document.getElementById("volbox-3").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 64) {
      (function () {
        document.getElementById("volbox-2").style.backgroundColor =
          "lightgreen";
      })();
    }
    if (volume > 32) {
      (function () {
        document.getElementById("volbox-1").style.backgroundColor =
          "lightgreen";
      })();
    }
  }
}

//this is the callback error function
function errorCallback(error) {
  console.log("navigator.getUserMedia error: ", error);
}
