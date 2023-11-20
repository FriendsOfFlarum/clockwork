import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import LinkButton from 'flarum/common/components/LinkButton';

export default class ClockworkPage extends ExtensionPage {
  content() {
    const clockworkUrl = app.forum.attribute('baseUrl') + '/__clockwork/app';

    return (
      <div className="ClockworkPage">
        <div className="container">
          <LinkButton className="Button" icon="fas fa-external-link-alt" href={clockworkUrl} external={true} target="_blank">
            {app.translator.trans('fof-clockwork.admin.dashboard.button_label')}
          </LinkButton>
        </div>
      </div>
    );
  }
}
