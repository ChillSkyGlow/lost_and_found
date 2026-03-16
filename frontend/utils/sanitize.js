/**
 * 安全函数：对HTML内容进行转义，防止XSS攻击。
 * @param {string} str - 需要转义的字符串
 * @returns {string} - 转义后的安全字符串
 */
export function sanitizeHTML(str) {
    if (str === null || str === undefined) {
        return '';
    }
    return str.toString().replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
} 