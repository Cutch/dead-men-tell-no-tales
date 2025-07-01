import { defineConfig } from 'eslint/config';
import eslintConfigPrettier from 'eslint-config-prettier/flat';
import globals from 'globals';

export default defineConfig([
  eslintConfigPrettier,
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
        $: 'readonly',
        jQuery: 'readonly',
        _: 'readonly',
        gameui: 'readonly',
      },
    },
    rules: {
      'arrow-parens': 'off',
      strict: 'off',
      'comma-dangle': 'off',
      'max-len': 'off',
      'object-curly-spacing': 'off',
      indent: 'off',
      'require-jsdoc': 'off',
      'one-var': 'off',
      'no-unused-vars': 'warn',
      'no-undef': 'error',
      'prefer-const': 'warn',
      'no-invalid-this': 'off',
      'operator-linebreak': 'off',
      'prefer-rest-params': 'warn',
      quotes: 'off',
      'quote-props': 'off',
      'new-cap': 'warn',
      'space-before-function-paren': 'off',
      'no-nested-ternary': 'off',
    },
  },
]);
