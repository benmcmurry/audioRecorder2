'use strict'
var constraints = {
    audio: true,
    video: false,
    // video: {
    //     width: {
    //         min: 640,
    //         ideal: 640,
    //         max: 640
    //     },
    //     height: {
    //         min: 480,
    //         ideal: 480,
    //         max: 480
    //     },
    //     framerate: 60
    // }
};
var safari;
if (navigator.userAgent.indexOf('Safari') != -1 && navigator.userAgent.indexOf('Chrome') == -1) {
    safari = true;
}
var liveVideoElement = document.querySelector('#live');
var playbackVideoElement = document.querySelector('#playback');
liveVideoElement.controls = false;
var mediaRecorder;
var chunks = [];
var count = 0;
var localStream = null;
var soundMeter = null; //not in use right now
var containerType = "video/webm";
var analyser; //variable for visualizing sound
var scriptProcessor; //variable for visualizing sound
var input; //variable for visualizing sound
var recordingStatus;
var testButton = document.querySelector("#testButton");
var buttons = document.querySelector("#buttons");
var prompt = document.querySelector("#prompt");
var timerContainer = document.querySelector("#timerContainer");

if (!navigator.mediaDevices.getUserMedia) {
    alert('navigator.mediaDevices.getUserMedia not supported on your browser, use the latest version of Firefox or Chrome');
} else {
    if (window.MediaRecorder == undefined) {
        alert('MediaRecorder not supported on your browser, use the latest version of Firefox or Chrome');
    } else {
        navigator.mediaDevices.getUserMedia(constraints)
            .then(function (stream) {
                localStream = stream;

                localStream.getTracks().forEach(function (track) {
                    if (track.kind == "audio") {
                        track.onended = function (event) {
                            console.log("audio track.onended Audio track.readyState=" + track.readyState + ", track.muted=" + track.muted);
                        }
                    }
                    if (track.kind == "video") {
                        track.onended = function (event) {
                            console.log("video track.onended Audio track.readyState=" + track.readyState + ", track.muted=" + track.muted);
                        }
                    }
                });

                liveVideoElement.srcObject = localStream;
                liveVideoElement.style.transform = 'scale(-1, 1)';
                liveVideoElement.play();

                try {
                    window.AudioContext = window.AudioContext || window.webkitAudioContext;
                    window.audioContext = new AudioContext();
                    visualizationOfSound(stream);
                } catch (e) {
                    console.log('Web Audio API not supported.' + e);
                }



            }).catch(function (err) {
                /* handle the error */
                console.log('navigator.getUserMedia error: ' + err);
            });
    }
}

function testStartRecording() {
    if (localStream == null) {
        alert('Could not get local stream from mic/camera');
    } else {
        testButton.innerHTML = "Please Speak";
        testButton.classList.remove("btn-success");
        testButton.classList.add("btn-danger");

        chunks = [];

        /* use the stream */
        console.log('Start recording...');
        if (typeof MediaRecorder.isTypeSupported == 'function') {
            /*
            	MediaRecorder.isTypeSupported is a function announced in https://developers.google.com/web/updates/2016/01/mediarecorder and later introduced in the MediaRecorder API spec http://www.w3.org/TR/mediastream-recording/
            */
            if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
                var options = {
                    mimeType: 'video/webm;codecs=vp9'
                };
            } else if (MediaRecorder.isTypeSupported('video/webm;codecs=h264')) {
                var options = {
                    mimeType: 'video/webm;codecs=h264'
                };
            } else if (MediaRecorder.isTypeSupported('video/webm')) {
                var options = {
                    mimeType: 'video/webm'
                };
            } else if (MediaRecorder.isTypeSupported('video/mp4')) {
                //Safari 14.0.2 has an EXPERIMENTAL version of MediaRecorder enabled by default
                containerType = "video/mp4";
                var options = {
                    mimeType: 'video/mp4'
                };
            }
            console.log('Using ' + options.mimeType);
            mediaRecorder = new MediaRecorder(localStream, options);
        } else {
            console.log('isTypeSupported is not supported, using default codecs for browser');
            mediaRecorder = new MediaRecorder(localStream);
        }
        mediaRecorder.ondataavailable = function (e) {
            console.log('mediaRecorder.ondataavailable, e.data.size=' + e.data.size);
            if (e.data && e.data.size > 0) {
                chunks.push(e.data);
            }
        };

        mediaRecorder.onerror = function (e) {
            console.log('mediaRecorder.onerror: ' + e);
        };

        mediaRecorder.onstart = function () {
            console.log('mediaRecorder.onstart, mediaRecorder.state = ' + mediaRecorder.state);

            localStream.getTracks().forEach(function (track) {
                if (track.kind == "audio") {
                    console.log("onstart - Audio track.readyState=" + track.readyState + ", track.muted=" + track.muted);
                }
                if (track.kind == "video") {
                    console.log("onstart - Video track.readyState=" + track.readyState + ", track.muted=" + track.muted);
                }
            });

        };

        mediaRecorder.onstop = function () {
            console.log('mediaRecorder.onstop, mediaRecorder.state = ' + mediaRecorder.state);
            testButton.innerHTML = "Re-Test Microphone";
            testButton.classList.remove("btn-danger");
            testButton.classList.add("btn-success");
            var recording = new Blob(chunks, {
                type: mediaRecorder.mimeType
            });

            playbackVideoElement.src = URL.createObjectURL(recording);

            if (safari == true) {
                playbackVideoElement.controls = true;
            } else {
                playbackVideoElement.play();
            }

            var rand = Math.floor((Math.random() * 10000000));
            switch (containerType) {
                case "video/mp4":
                    var name = "video_" + rand + ".mp4";
                    break;
                default:
                    var name = "video_" + rand + ".webm";
            }

            // downloadLink.innerHTML = 'Download ' + name;

            // downloadLink.setAttribute("download", name);
            // downloadLink.setAttribute("name", name);
        };

        mediaRecorder.onpause = function () {
            console.log('mediaRecorder.onpause, mediaRecorder.state = ' + mediaRecorder.state);
        }

        mediaRecorder.onresume = function () {
            console.log('mediaRecorder.onresume, mediaRecorder.state = ' + mediaRecorder.state);
        }

        mediaRecorder.onwarning = function (e) {
            console.log('mediaRecorder.onwarning: ' + e);
        };

        // pauseResBtn.textContent = "Pause";

        mediaRecorder.start(1000);

        localStream.getTracks().forEach(function (track) {
            console.log(track.kind + ":" + JSON.stringify(track.getSettings()));
            console.log(track.getSettings());
        });
        (function () {
            setTimeout(function () {

                console.log("stop recording");
                mediaRecorder.stop();
            }, 5000);

        })();
    }
}
function startRecording() {
    buttons.classList.add("d-none");
    prompt.classList.remove("d-none");
    timer_container.classList.remove("d-none");
    
    timer(prepare_time, "Prepare");

    setTimeout(function() {
timer(response_time, "Recording")
    }, prepare_time * 1000 +1000);

}
function timer(time, timerType) {

    (function move() { // this keeps the counter running in a loop based on changed information
        if (time > -1) {

            document.getElementById("timer").innerHTML =timerType + " " + time + "s";
            setTimeout(move, 1000);
            time = time - 1;
        }

    })();
}
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
            var elements = document.getElementsByClassName('volbox');
            for (var i = 0; i < elements.length; i++) {
                elements[i].style.backgroundColor = "white";
            }
        })();

        if (volume > 320) {
            (function () {
                document.getElementById('volbox-12').style.backgroundColor = "red";
            })();
        }
        if (volume > 304) {
            (function () {
                document.getElementById('volbox-11').style.backgroundColor = "yellow";
            })();
        }
        if (volume > 288) {
            (function () {
                document.getElementById('volbox-10').style.backgroundColor = "yellow";
            })();
        }
        if (volume > 240) {
            (function () {
                document.getElementById('volbox-9').style.backgroundColor = "yellow";
            })();
        }
        if (volume > 224) {
            (function () {
                document.getElementById('volbox-8').style.backgroundColor = "yellow";
            })();
        }
        if (volume > 192) {
            (function () {
                document.getElementById('volbox-7').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 176) {
            (function () {
                document.getElementById('volbox-6').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 144) {
            (function () {
                document.getElementById('volbox-5').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 128) {
            (function () {
                document.getElementById('volbox-4').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 96) {
            (function () {
                document.getElementById('volbox-3').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 64) {
            (function () {
                document.getElementById('volbox-2').style.backgroundColor = "lightgreen";
            })();
        }
        if (volume > 32) {
            (function () {
                document.getElementById('volbox-1').style.backgroundColor = "lightgreen";
            })();
        }


        //   if (volume < 250) {$("#volume').style.backgroundColor = "lightgreen";}
        //   if (volume > 249) { $("#volume').style.backgroundColor = "yellow";}
        //   if (volume > 320) {volume = 320; $("#volume').style.backgroundColor = "red";;}
        //   console.log (volume);
        //   $("#volume').style.width", volume);


    }
}

function errorCallback(error) {
    console.log('navigator.getUserMedia error: ', error);
}