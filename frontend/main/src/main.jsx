import React from 'react';
import ReactDOM from 'react-dom/client';
import './i18n';
import './styles/global.css';
import App from './App';

import { initializeTheme } from './components/ThemeToggle';

initializeTheme();

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
