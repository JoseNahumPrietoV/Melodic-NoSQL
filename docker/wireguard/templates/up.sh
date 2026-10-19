#!/usr/bin/env sh
set -eu

interface="$1"
address="$(ip -4 -o addr show "$interface" | awk '{ print $4 }' | cut -d/ -f1)"
mongodb="$(getent hosts mongodb | awk '{ print $1 }')"

iptables -t nat -A PREROUTING -i "$interface" -d "$address" -p tcp --dport 27017 -j DNAT --to-destination "$mongodb:27017"
iptables -t nat -A POSTROUTING -o eth+ -j MASQUERADE
iptables -A FORWARD -o "$interface" -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
iptables -A FORWARD -i "$interface" -p tcp -d "$mongodb" --dport 27017 -j ACCEPT
iptables -A FORWARD -i "$interface" -p tcp --dport 22 -j ACCEPT
iptables -A FORWARD -i "$interface" -j DROP
