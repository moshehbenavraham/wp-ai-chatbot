/**
 * Vitest setup — polyfill localStorage for Node.js v22+.
 *
 * Node.js v22+ defines a native globalThis.localStorage stub that requires
 * --localstorage-file to function. Without it, all methods are undefined.
 * happy-dom cannot override the native getter, so tests that call
 * localStorage.clear() etc. fail.
 *
 * This setup replaces the broken native stub with a simple in-memory
 * implementation before tests run.
 */

class MemoryStorage {
	constructor() {
		this._data = Object.create(null);
	}
	get length() {
		return Object.keys(this._data).length;
	}
	key(index) {
		return Object.keys(this._data)[index] ?? null;
	}
	getItem(key) {
		return Object.prototype.hasOwnProperty.call(this._data, key) ? this._data[key] : null;
	}
	setItem(key, value) {
		this._data[key] = String(value);
	}
	removeItem(key) {
		delete this._data[key];
	}
	clear() {
		this._data = Object.create(null);
	}
}

// Only patch if the native localStorage is broken (methods are undefined).
if (typeof globalThis.localStorage === 'undefined' || typeof globalThis.localStorage?.clear !== 'function') {
	const storage = new MemoryStorage();
	Object.defineProperty(globalThis, 'localStorage', {
		value: storage,
		writable: true,
		configurable: true,
		enumerable: true,
	});
}
