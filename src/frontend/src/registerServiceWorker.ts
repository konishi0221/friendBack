
export function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    if (import.meta.env.PROD || !import.meta.env.DEV) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/custom-sw.js')
          .then(registration => {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
            
            registration.update();
            
            registration.onupdatefound = () => {
              const installingWorker = registration.installing;
              if (installingWorker) {
                installingWorker.onstatechange = () => {
                  if (installingWorker.state === 'installed') {
                    if (navigator.serviceWorker.controller) {
                      console.log('New content is available; please refresh.');
                    } else {
                      console.log('Content is cached for offline use.');
                    }
                  }
                };
              }
            };
          })
          .catch(error => {
            console.error('ServiceWorker registration failed: ', error);
          });
      });
    } else {
      console.log('Service worker registration skipped in development mode');
    }
  }
}

export function isLaunchedFromHomeScreen() {
  return window.matchMedia('(display-mode: standalone)').matches || 
         (window.navigator as any).standalone === true;
}
