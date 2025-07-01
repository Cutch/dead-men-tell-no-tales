import { nodeResolve } from '@rollup/plugin-node-resolve';

export default {
  input: 'src/deadmentellnotales.js',
  output: {
    file: 'dist/deadmentellnotales.js',
    format: 'es',
    banner: '/// <amd-module name="bgagame/deadmentellnotales"/>',
  },
  plugins: [nodeResolve()],
};
