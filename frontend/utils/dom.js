/**
 * A shorthand for document.querySelector.
 * @param {string} selector The CSS selector to query.
 * @returns {HTMLElement|null}
 */
export const $ = (selector) => document.querySelector(selector);

/**
 * A shorthand for document.querySelectorAll.
 * @param {string} selector The CSS selector to query.
 * @returns {NodeListOf<HTMLElement>}
 */
export const $$ = (selector) => document.querySelectorAll(selector);

/**
 * Displays a message in a specified container.
 * @param {string|HTMLElement} container The ID of the element or the element itself.
 * @param {string} type 'success' or 'error'.
 * @param {string} message The message to display.
 */
export function showMessage(container, type, message) {
    const box = (typeof container === 'string') ? document.getElementById(container) : container;
    if (box) {
        box.className = `message ${type}`;
        box.textContent = message;
        box.style.display = 'block';
    }
}

/**
 * Hides a message in a specified container.
 * @param {string|HTMLElement} container The ID of the element or the element itself.
 */
export function hideMessage(container) {
    const box = (typeof container === 'string') ? document.getElementById(container) : container;
    if (box) {
        box.style.display = 'none';
        box.textContent = '';
    }
} 