#!/usr/bin/env sh
set -eu

iptables -t nat -F PREROUTING
iptables -t nat -F POSTROUTING
iptables -F FORWARD
