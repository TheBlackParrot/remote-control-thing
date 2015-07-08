#!/bin/bash

case $1 in
        elapsed1)
		mpc status | awk '/^\[playing\]/ { sub(/\/.+/,"",$3); split($3,a,/:/); print a[1]*60+a[2] }'
		;;
	elapsed2)
		mpc status | awk '/^\[paused\]/ { sub(/\/.+/,"",$3); split($3,a,/:/); print a[1]*60+a[2] }'
		;;
        *)
		echo "Usage: $0 [command]"
		;;
esac
