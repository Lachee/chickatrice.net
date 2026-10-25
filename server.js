// Production entry point: `node server.js`, in place of adapter-node's `node build`.
//
// adapter-node 6 dropped the ORIGIN variable: it builds each request's URL from
// the Host header and assumes https unless PROTOCOL_HEADER names a header to
// read the scheme from. Behind a TLS proxy that is right, but a plain-http
// deployment (`docker compose up` on localhost) then fails SvelteKit's CSRF
// check on every form, and providers get https callback URLs. When ORIGIN is
// set, this stamps it onto every request so the app sees that origin instead.
// It goes in the X-Forwarded-* headers because Auth.js reads those itself when
// it works out its own address; both then agree on it, whatever the client sent.
import http from 'node:http';
import process from 'node:process';

const origin = process.env.ORIGIN ? new URL(process.env.ORIGIN) : null;

// adapter-node turns away bodies over 512K, well under config.yaml's
// avatar.maxUploadBytes (5 MB by default). Raise it if that is raised.
process.env.BODY_SIZE_LIMIT ??= '6M';
if (origin) {
	process.env.PROTOCOL_HEADER = 'x-forwarded-proto';
	process.env.HOST_HEADER = 'x-forwarded-host';
}

// Imported after the variables above are set: the handler reads them on load.
const { handler } = await import('./build/handler.js');

const server = http.createServer((req, res) => {
	if (origin) {
		req.headers['x-forwarded-proto'] = origin.protocol.slice(0, -1);
		req.headers['x-forwarded-host'] = origin.host;
	}
	handler(req, res, () => {
		res.statusCode = 404;
		res.end('Not found');
	});
});

const port = Number(process.env.PORT ?? 3000);
const host = process.env.HOST ?? '0.0.0.0';
server.listen(port, host, () => console.log(`Listening on http://${host}:${port}${origin ? ` as ${origin.origin}` : ''}`));

function shutdown() {
	server.close(() => process.exit(0));
	server.closeIdleConnections();
	setTimeout(() => process.exit(0), 30_000).unref();
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
