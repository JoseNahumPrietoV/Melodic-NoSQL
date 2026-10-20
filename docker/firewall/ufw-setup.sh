#!/usr/bin/env sh
set -eu

rules="$(dirname "$0")/docker-user.rules"
after_rules=/etc/ufw/after.rules
vpn_subnet="${VPN_SUBNET:-172.30.80.0/24}"
begin='# BEGIN MELODIC DOCKER-USER'
end='# END MELODIC DOCKER-USER'

ufw default deny incoming
ufw default allow outgoing
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 51820/udp
ufw allow proto tcp from "$vpn_subnet" to any port 22

sed -i "/^$begin\$/,/^$end\$/d" "$after_rules"
{
    echo "$begin"
    cat "$rules"
    echo "$end"
} >> "$after_rules"

ufw --force enable
ufw reload
