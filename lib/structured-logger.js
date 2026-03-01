/**
 * Structured Logger — AI-friendly JSON logging with error capture.
 *
 * Usage (Node.js / test environment):
 *   const { createLogger } = require('./lib/structured-logger');
 *   const log = createLogger('my-component');
 *   log.info('Processing started', { batchSize: 50 });
 *   log.captureError(new Error('Something failed'), { userId: 42 });
 */

const fs = require('node:fs');
const path = require('node:path');

const LOGS_DIR = path.resolve(__dirname, '..', 'logs');

const LEVELS = ['debug', 'info', 'warn', 'error'];

/**
 * Build a structured JSON log entry.
 * @param {string} level
 * @param {string} component
 * @param {string} msg
 * @param {object} context
 * @returns {object}
 */
function buildEntry(level, component, msg, context) {
	const entry = {
		timestamp: new Date().toISOString(),
		level,
		component,
		msg,
	};
	if (context && Object.keys(context).length > 0) {
		entry.context = context;
	}
	return entry;
}

/**
 * Create a structured logger for a given component.
 * @param {string} component  Logical name for the emitting component.
 * @param {string} [logsDir]  Override logs directory (defaults to project root /logs).
 * @returns {object} Logger with debug, info, warn, error, and captureError methods.
 */
function createLogger(component = 'app', logsDir = LOGS_DIR) {
	const logger = {};

	for (const level of LEVELS) {
		logger[level] = (msg, context = {}) => {
			const entry = buildEntry(level, component, msg, context);
			const json = JSON.stringify(entry);
			process.stderr.write(`${json}\n`);
		};
	}

	/**
	 * Capture an error and write it to logs/last_error_<timestamp>.json.
	 * @param {Error} err       The error to capture.
	 * @param {object} context  Extra context to attach.
	 * @returns {string|null}   Path to the written file, or null on failure.
	 */
	logger.captureError = (err, context = {}) => {
		const timestamp = new Date().toISOString();
		const safeTs = timestamp.replace(/:/g, '-');

		const payload = {
			timestamp,
			level: 'error',
			component,
			msg: err.message,
			error: {
				type: err.constructor.name,
				message: err.message,
				stack: err.stack || '',
			},
			context,
		};

		const json = JSON.stringify(payload, null, 2);
		const filePath = path.join(logsDir, `last_error_${safeTs}.json`);

		try {
			if (!fs.existsSync(logsDir)) {
				fs.mkdirSync(logsDir, { recursive: true });
			}
			fs.writeFileSync(filePath, `${json}\n`);

			logger.error(`Error captured: ${err.message}`, {
				errorType: err.constructor.name,
				errorFile: filePath,
			});

			return filePath;
		} catch (writeErr) {
			process.stderr.write(
				`${JSON.stringify({
					timestamp,
					level: 'error',
					component,
					msg: `Failed to write error capture file: ${writeErr.message}`,
				})}\n`,
			);
			return null;
		}
	};

	return logger;
}

module.exports = { createLogger };
