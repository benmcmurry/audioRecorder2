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
var analyser;
var scriptProcessor;
var input;

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
                    console.log(window.audioContext);
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
    console.log("ProcesInput Started");
    var recordingStatus = true;
    if (recordingStatus){
    var soundArray = new Uint8Array(analyser.frequencyBinCount);
var volume;
      analyser.getByteFrequencyData(soundArray);
      length = soundArray.length;
      let values = 0;  
      let i = 0;
      for (; i < length; i++) {
          values +=soundArray[i];
          }
          volume = (values / length) *3 + 10;
          (function() {
            var elements = document.getElementsByClassName('volbox');
            for (var i = 0; i < elements.length; i++) {
                elements[i].style.backgroundColor="white";
            }
            // document.getElementsByClassName('volbox').style.backgroundColor = "white";
        })();
       
        
          if(volume > 320 ) {(function() {document.getElementsByClassName('volbox-20').style.backgroundColor = "red";})();}
          if(volume > 304 ) {(function() {document.getElementsByClassName('volbox-19').style.backgroundColor = "red";})();}
          if(volume > 288 ) {(function() {document.getElementsByClassName('volbox-18').style.backgroundColor = "yellow";})();}
          if(volume > 272 ) {(function() {document.getElementsByClassName('volbox-17').style.backgroundColor = "yellow";})();}
          if(volume > 256 ) {(function() {document.getElementsByClassName('volbox-16').style.backgroundColor = "yellow";})();}
          if(volume > 240) {(function() {document.getElementsByClassName('volbox-15').style.backgroundColor = "yellow";})();}
          if(volume > 224 ) {(function() {document.getElementsByClassName('volbox-14').style.backgroundColor = "yellow";})();}
          if(volume > 208 ) {(function() {document.getElementsByClassName('volbox-13').style.backgroundColor = "green";})();}
          if(volume > 192 ) {(function() {document.getElementsByClassName('volbox-12').style.backgroundColor = "green";})();}
          if(volume > 176 ) {(function() {document.getElementsByClassName('volbox-11').style.backgroundColor = "green";})();}
          if(volume > 160 ) {(function() {document.getElementsByClassName('volbox-10').style.backgroundColor = "green";})();}
          if(volume > 144 ) {(function() {document.getElementsByClassName('volbox-9').style.backgroundColor = "green";})();}
          if(volume > 128 ) {(function() {document.getElementsByClassName('volbox-8').style.backgroundColor = "green";})();}
          if(volume > 112 ) {(function() {document.getElementsByClassName('volbox-7').style.backgroundColor = "green";})();}
          if(volume > 96 ) {(function() {document.getElementsByClassName('volbox-6').style.backgroundColor = "green";})();}
          if(volume > 80 ) {(function() {document.getElementsByClassName('volbox-5').style.backgroundColor = "green";})();}
          if(volume > 64 ) {(function() {document.getElementsByClassName('volbox-4').style.backgroundColor = "green";})();}
          if(volume > 48 ) {(function() {document.getElementsByClassName('volbox-3').style.backgroundColor = "green";})();}
          if(volume > 32 ) {(function() {document.getElementsByClassName('volbox-2').style.backgroundColor = "green";})();}
          if(volume > 16 ) {(function() {document.getElementsByClassName('volbox-1').style.backgroundColor = "green";})();}

          
        //   if (volume < 250) {$("#volume').style.backgroundColor = "green";}
        //   if (volume > 249) { $("#volume').style.backgroundColor = "yellow";}
        //   if (volume > 320) {volume = 320; $("#volume').style.backgroundColor = "red";;}
        //   console.log (volume);
        //   $("#volume').style.width", volume);

    
        }
}
