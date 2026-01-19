import { startStimulusApp } from '@symfony/stimulus-bridge';

// Registers Stimulus controllers from controllers.json and in the controllers/ directory
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
));

// Debug: log registered controllers
console.log('[Stimulus] App started');

// Re-connect controllers after Turbo navigation
document.addEventListener('turbo:load', () => {
    console.log('[Stimulus] turbo:load - reconnecting controllers');
});

document.addEventListener('turbo:render', () => {
    console.log('[Stimulus] turbo:render');
});
