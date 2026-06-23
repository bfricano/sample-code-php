#!/usr/bin/env python3
"""
APEX Trader — Development Server
Proxies requests: /api/* → PHP built-in server, rest → PHP built-in server
Usage: python3 serve.py [port]
"""
import os, sys, subprocess, signal, time, threading, http.server, socketserver, urllib.request, urllib.error

PORT     = int(sys.argv[1]) if len(sys.argv) > 1 else 8000
PHP_PORT = PORT + 1
ROOT     = os.path.join(os.path.dirname(__file__), 'public')

# Start PHP built-in server
php_proc = subprocess.Popen(
    ['php', '-S', f'127.0.0.1:{PHP_PORT}', 'index.php'],
    cwd=ROOT,
    stdout=subprocess.DEVNULL,
    stderr=subprocess.DEVNULL,
)

def cleanup(sig=None, frame=None):
    php_proc.terminate()
    sys.exit(0)

signal.signal(signal.SIGINT,  cleanup)
signal.signal(signal.SIGTERM, cleanup)

class Handler(http.server.BaseHTTPRequestHandler):
    def do_request(self):
        target = f'http://127.0.0.1:{PHP_PORT}{self.path}'
        headers = {k: v for k, v in self.headers.items()}
        body = None
        if self.command in ('POST', 'PUT', 'PATCH'):
            length = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(length) if length else b''

        req = urllib.request.Request(target, data=body, headers=headers, method=self.command)
        try:
            with urllib.request.urlopen(req) as resp:
                self.send_response(resp.status)
                for k, v in resp.headers.items():
                    if k.lower() not in ('transfer-encoding', 'connection'):
                        self.send_header(k, v)
                self.end_headers()
                self.wfile.write(resp.read())
        except urllib.error.HTTPError as e:
            self.send_response(e.code)
            for k, v in e.headers.items():
                if k.lower() not in ('transfer-encoding', 'connection'):
                    self.send_header(k, v)
            self.end_headers()
            self.wfile.write(e.read())
        except Exception as ex:
            self.send_response(502)
            self.end_headers()
            self.wfile.write(str(ex).encode())

    do_GET    = do_request
    do_POST   = do_request
    do_PUT    = do_request
    do_DELETE = do_request
    do_OPTIONS= do_request

    def log_message(self, fmt, *args):
        pass  # suppress default logging

time.sleep(0.5)  # wait for PHP server
with socketserver.TCPServer(('', PORT), Handler) as httpd:
    httpd.allow_reuse_address = True
    print(f'\n  ▲ APEX Trader running at http://localhost:{PORT}')
    print(f'  PHP backend on port {PHP_PORT}')
    print(f'  Press Ctrl+C to stop\n')
    httpd.serve_forever()
