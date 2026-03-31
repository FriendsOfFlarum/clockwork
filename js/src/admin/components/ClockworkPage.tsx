import app from 'flarum/admin/app';
import Alert from 'flarum/common/components/Alert';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Form from 'flarum/common/components/Form';
import FormSection from 'flarum/admin/components/FormSection';
import FormSectionGroup from 'flarum/admin/components/FormSectionGroup';
import LinkButton from 'flarum/common/components/LinkButton';
import Switch from 'flarum/common/components/Switch';

export default class ClockworkPage extends ExtensionPage {
  content() {
    const clockworkUrl = app.forum.attribute('baseUrl') + '/__clockwork/app';
    const disableAuth = this.setting('fof-clockwork.disable_auth');

    return (
      <div className="ClockworkPage">
        <div className="container">
          <FormSectionGroup>
            <FormSection label={app.translator.trans('fof-clockwork.admin.dashboard.section_label')}>
              <div className="Form-group">
                <LinkButton className="Button" icon="fas fa-external-link-alt" href={clockworkUrl} external={true} target="_blank">
                  {app.translator.trans('fof-clockwork.admin.dashboard.button_label')}
                </LinkButton>
              </div>
            </FormSection>

            <FormSection label={app.translator.trans('fof-clockwork.admin.settings.section_label')}>
              <Form>
                {disableAuth() === '1' && (
                  <div className="Form-group">
                    <Alert type="warning" dismissible={false}>
                      {app.translator.trans('fof-clockwork.admin.settings.disable_auth_warning')}
                    </Alert>
                  </div>
                )}
                <div className="Form-group">
                  <Switch state={disableAuth() === '1'} onchange={(val: boolean) => disableAuth(val ? '1' : '0')}>
                    {app.translator.trans('fof-clockwork.admin.settings.disable_auth_label')}
                  </Switch>
                  <div className="helpText">{app.translator.trans('fof-clockwork.admin.settings.disable_auth_help')}</div>
                </div>
                <div className="Form-group Form-controls">{this.submitButton()}</div>
              </Form>
            </FormSection>
          </FormSectionGroup>
        </div>
      </div>
    );
  }
}
