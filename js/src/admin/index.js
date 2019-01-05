import app from 'flarum/app';

app.initializers.add('reflar/clockwork', () => {
  console.log('Hello, admin!');
});
