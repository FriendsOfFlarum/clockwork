import app from 'flarum/admin/app';
import ClockworkPage from './components/ClockworkPage';

app.initializers.add('fof/clockwork', () => {
  app.extensionData.for('fof-clockwork').registerPage(ClockworkPage);
});
