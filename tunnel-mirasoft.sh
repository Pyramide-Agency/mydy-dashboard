#!/bin/bash
# Mirasoft tunnel — mirasoft.pyramide.uz → localhost:8000
cloudflared tunnel --config ~/.cloudflared/mirasoft.pyramide.uz.yml run
