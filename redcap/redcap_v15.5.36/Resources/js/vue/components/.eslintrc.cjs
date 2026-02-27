/* eslint-env node */
require('@rushstack/eslint-patch/modern-module-resolution')

module.exports = {
    root: true,
    env: {
        node: true,
        commonjs: true,
        browser: true,
        es6: true,
    },
    extends: [
        'plugin:vue/vue3-essential',
        'eslint:recommended',
        '@vue/eslint-config-prettier',
    ],
    parserOptions: {
        ecmaVersion: 'latest',
    },
    rules: {
        'no-unused-vars': [
            'off',
            { vars: 'all', args: 'after-used', ignoreRestSiblings: false },
        ],
        'max-len': ['off', { code: 100 }],
    },
}
