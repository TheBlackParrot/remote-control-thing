var conn = new WebSocket('ws://192.168.1.151:8080');
conn.onopen = function(e) {
	console.log("Connection established!");
	conn.send("stat^tload");
	conn.send("stat^tcpuTemp");
	conn.send("stat^tkernel");
	conn.send("VLC^topen");
	conn.send("vol^tget");
};

conn.onmessage = function(e) {
	handleReturn(e.data);
};

var vlcIsOpen = 0;
var	hasRan;
function handleReturn(data) {
	var jsonData = JSON.parse(data);

	switch(jsonData.type) {
		case "load":
			$(".loadavg").text(jsonData[0] + ", " + jsonData[1] + ", " + jsonData[2]);
			break;
		
		case "vol":
			$(".vol-val").text(jsonData[0] + "%");
			$("#master").slider("value",jsonData[0]);
			break;
		
		case "cpuTemp":
			$(".cpuTemp").html(jsonData[0] + "&deg;C");
			break;
		
		case "kernel":
			$(".kernel").text(jsonData[0]);
			break;
		
		case "TwitchURL":
		case "YouTubeURL":
			$("#ytInput").addClass("nouse");
			$("#ytInput").attr("disabled","disabled");
			$("#ytSub").addClass("nouse");
			$("#twitchInput").addClass("nouse");
			$("#twitchInput").attr("disabled","disabled");
			$("#twitchSub").addClass("nouse");
			break;

		case "VLCOpen":
			if(jsonData[0] == "1" && vlcIsOpen == 1) {
				return;
			}
			if(jsonData[0] == "1") {
				conn.send("VLC^tlength");
				conn.send("VLC^telapsed");
				conn.send("VLC^paused");
				conn.send("VLC^ttitle");
				vlcIsOpen = 1;
				$("#vlc-section").removeClass("nouse");
				$("#vlc-seek").slider("enable");
				$("#youtube-section").addClass("nouse");
				$("#ytInput").attr("disabled","disabled");
				$("#ytSub").addClass("nouse");
				$("#twitch-section").addClass("nouse");
				$("#twitchInput").attr("disabled","disabled");
				$("#twitchSub").addClass("nouse");
			} else {
				if(vlcIsOpen == 1 || !hasRan) {
					$("#vlc-title").text("");
					$("#vlc-section").addClass("nouse");
					$("#vlc-seek").slider("disable");
					$("#youtube-section").removeClass("nouse");
					$("#ytInput").removeAttr("disabled");
					$("#ytSub").removeClass("nouse");
					$("#twitch-section").removeClass("nouse");
					$("#twitchInput").removeAttr("disabled");
					$("#twitchSub").removeClass("nouse");
					$("#vlc-time").text("00:00");
					$("#vlc-elapsed").text("00:00");
					vlcIsOpen = 0;
					hasRan = 1;
				}
			}
			break;

		case "VLCElapsed":
			if(!seeking) {
				seekVLC(jsonData[0]);
			}
			break;

		case "VLCLength":
			$(".vlc-time").text(jsonData[0].toMMSS());
			$(".vlc-time").attr("timeval",jsonData[0]);
			break;

		case "VLCPaused":
			if(jsonData[0] == "1") {
				$(".vlc-pause-icon").removeClass("fa-pause");
				$(".vlc-pause-icon").addClass("fa-play");
			} else {
				$(".vlc-pause-icon").removeClass("fa-play");
				$(".vlc-pause-icon").addClass("fa-pause");
			}
			break;

		case "VLCTitle":
			$("#vlc-title").text(jsonData[0]);
			break;
	}
}

setInterval(function(){
	if(conn.OPEN) {
		if(vlcIsOpen) {
			conn.send("VLC^telapsed");
			conn.send("VLC^tlength");
		}
	}
},1000)

setInterval(function(){
	if(conn.OPEN) {
		conn.send("stat^tload");
		conn.send("VLC^topen");
	}
},2000)

setInterval(function(){
	if(conn.OPEN) {
		conn.send("stat^tcpuTemp");
	}
},5000)