"""
Reverse proxy to serve Kypre marketplace.
Forwards requests to PHP's built-in server with the correct Host header.
"""
import http.server
import http.client
import sys

PHP_HOST = "127.0.0.1"
PHP_PORT = 8001

class ProxyHandler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self._proxy()

    def do_POST(self):
        self._proxy()

    def _proxy(self):
        body = None
        if self.command == "POST":
            length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(length)

        conn = http.client.HTTPConnection(PHP_HOST, PHP_PORT)
        headers = {}
        for key, val in self.headers.items():
            if key.lower() != "host":
                headers[key] = val
        headers["Host"] = "localhost"

        conn.request(self.command, self.path, body=body, headers=headers)
        resp = conn.getresponse()
        resp_body = resp.read()

        self.send_response(resp.status)
        for key, val in resp.getheaders():
            if key.lower() not in ("transfer-encoding", "connection"):
                self.send_header(key, val)
        self.end_headers()
        self.wfile.write(resp_body)
        conn.close()

    def log_message(self, format, *args):
        pass

if __name__ == "__main__":
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8000
    server = http.server.HTTPServer(("0.0.0.0", port), ProxyHandler)
    print(f"Kypre proxy on port {port} -> PHP on {PHP_PORT}", flush=True)
    server.serve_forever()
