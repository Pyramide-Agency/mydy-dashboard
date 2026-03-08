#!/bin/bash
# Vektron tunnel — webmini.pyramide.uz → localhost:3000
cloudflared tunnel --config ~/.cloudflared/webmini.yml run
