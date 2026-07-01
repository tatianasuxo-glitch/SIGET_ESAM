import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    // Directorio donde Vite guardará los archivos compilados
    outDir: 'public/build',
    // Vacía el directorio de salida antes de cada build
    emptyOutDir: true,
    // Archivo de entrada principal de tu JS
    rollupOptions: {
      input: {
        main: 'src/js/main.js', // Ruta a tu JS principal
      },
    },
  },
  // Servidor de desarrollo (opcional, para recarga en caliente)
  server: {
    // Puerto donde correrá Vite en desarrollo
    port: 3000,
  },
});