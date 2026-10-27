import React from 'react';
import { createRoot } from 'react-dom/client';
import DashboardApp from './DashboardApp.jsx';

const appElement = document.getElementById('app');

if (appElement) {
    const root = createRoot(appElement);

    root.render(
        <React.StrictMode>
            <DashboardApp />
        </React.StrictMode>,
    );
}
