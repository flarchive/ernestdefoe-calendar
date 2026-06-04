import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

const pad = (n: number) => String(n).padStart(2, '0');

/**
 * Self-service birthday picker for the account settings page. Stores only month +
 * day (MM-DD) — never a year — so celebrating a birthday can't reveal someone's
 * age. Saving an empty selection clears it (fully opt-in / opt-out).
 */
const BirthdayField = {
  oninit(this: any) {
    const v = String((app.session.user && app.session.user.attribute('calendarBirthday')) || '');
    const [mm, dd] = v.split('-');
    this.mm = mm || '';
    this.dd = dd || '';
    this.saving = false;
  },

  view(this: any) {
    const months = Array.from({ length: 12 }, (_, i) => ({
      v: pad(i + 1),
      label: new Date(2000, i, 1).toLocaleDateString(undefined, { month: 'long' }),
    }));
    const days = Array.from({ length: 31 }, (_, i) => pad(i + 1));
    const dirty = this.mm + '-' + this.dd !== String((app.session.user?.attribute('calendarBirthday')) || '-');

    return m('.CalBirthday', [
      m('p.helpText', t('birthday_help')),
      m('.CalBirthday-row', [
        m('select.FormControl.CalBirthday-month', { value: this.mm, onchange: (e: any) => (this.mm = e.target.value) }, [
          m('option', { value: '' }, t('birthday_month')),
          ...months.map((mo) => m('option', { value: mo.v }, mo.label)),
        ]),
        m('select.FormControl.CalBirthday-day', { value: this.dd, onchange: (e: any) => (this.dd = e.target.value) }, [
          m('option', { value: '' }, t('birthday_day')),
          ...days.map((d) => m('option', { value: d }, d)),
        ]),
        Button.component(
          { className: 'Button', loading: this.saving, disabled: !dirty || (!!this.mm !== !!this.dd), onclick: () => this.save() },
          t('birthday_save')
        ),
      ]),
    ]);
  },

  save(this: any) {
    const user = app.session.user;
    if (!user) return;
    const value = this.mm && this.dd ? this.mm + '-' + this.dd : null;
    this.saving = true;
    m.redraw();
    user
      .save({ calendarBirthday: value })
      .then(() => { this.saving = false; app.alerts.show({ type: 'success' }, t('birthday_saved')); m.redraw(); })
      .catch(() => { this.saving = false; m.redraw(); });
  },
};

export default BirthdayField;
