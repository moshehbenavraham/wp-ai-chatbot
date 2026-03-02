import { defineConfig } from 'vitest/config';

export default defineConfig({
	test: {
		include: ['tests/js/**/*.test.js'],
		// Polyfill localStorage for Node.js v22+ (native stub lacks methods).
		setupFiles: ['tests/js/setup-localstorage.js'],
		// Default environment for Node-based tests (structured-logger, smoke).
		// Individual test files may override with @vitest-environment happy-dom.
		environment: 'node',
		environmentOptions: {
			happyDOM: {
				url: 'http://localhost/',
			},
		},
	},
});
