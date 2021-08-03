'use strict'
var constraints = {
	audio: true,
	video: {
		width: {
			min: 640,
			ideal: 640,
			max: 640
		},
		height: {
			min: 480,
			ideal: 480,
			max: 480
		},
		framerate: 60
	}
};
var liveVideoElement = document.querySelector('#live');
liveVideoElement.controls = false;
var mediaRecorder;
var chunks = [];
var count = 0;
var localStream = null;
var soundMeter  = null;
var containerType = "video/webm";

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
				liveVideoElement.play();
				try {
					window.AudioContext = window.AudioContext || window.webkitAudioContext;
					window.audioContext = new AudioContext();
                    console.log(audioContext);
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



function visualizationOfSound(stream) {
    console.log("VisualizationofSound started");
    // audioContext = new AudioContext();
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
function processInput () {
    if (recordingStatus){
    array = new Uint8Array(analyser.frequencyBinCount);

      analyser.getByteFrequencyData(array);
      length = array.length;
      let values = 0;  
      let i = 0;
      for (; i < length; i++) {
          values +=array[i];
          }
          volume = (values / length) *3 + 10;
         
          $(".volbox").css("background-color", "white");
       
        
          if(volume > 320 ) {$(".volbox-20").css("background-color", "red");}
          if(volume > 304 ) {$(".volbox-19").css("background-color", "red");}
          if(volume > 288 ) {$(".volbox-18").css("background-color", "yellow");}
          if(volume > 272 ) {$(".volbox-17").css("background-color", "yellow");}
          if(volume > 256 ) {$(".volbox-16").css("background-color", "yellow");}
          if(volume > 240) {$(".volbox-15").css("background-color", "yellow");}
          if(volume > 224 ) {$(".volbox-14").css("background-color", "yellow");}
          if(volume > 208 ) {$(".volbox-13").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 192 ) {$(".volbox-12").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 176 ) {$(".volbox-11").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 160 ) {$(".volbox-10").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 144 ) {$(".volbox-9").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 128 ) {$(".volbox-8").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 112 ) {$(".volbox-7").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 96 ) {$(".volbox-6").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 80 ) {$(".volbox-5").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 64 ) {$(".volbox-4").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 48 ) {$(".volbox-3").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 32 ) {$(".volbox-2").css("background-color", "rgb(129, 245, 129)");}
          if(volume > 16 ) {$(".volbox-1").css("background-color", "rgb(129, 245, 129)");}

          
        //   if (volume < 250) {$("#volume").css("background-color", "rgb(129, 245, 129)");}
        //   if (volume > 249) { $("#volume").css("background-color", "yellow");}
        //   if (volume > 320) {volume = 320; $("#volume").css("background-color", "red");}
        //   console.log (volume);
        //   $("#volume").css("width", volume);

    
        }
}
