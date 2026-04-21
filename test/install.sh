#!/bin/bash
composer install
pip install playwright
python3 -m playwright install --with-deps chromium
