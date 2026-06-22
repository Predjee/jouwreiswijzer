// Add project specific javascript code and import of additional bundles here:
import {formToolbarActionRegistry} from 'sulu-admin-bundle/views';
import {fieldRegistry} from 'sulu-admin-bundle/containers/Form';

import DownloadToolbarAction from './toolbarActions/DownloadToolbarAction.js';
import ReleasePdfToolbarAction from './toolbarActions/ReleasePdfToolbarAction.js';

import TravelPlanFeedback from './fields/TravelPlanFeedback.js';
import TravelPlanFeedbackSummary from './fields/TravelPlanFeedbackSummary.js';
import FormMailTextEditor from './fields/FormMailTextEditor.js';
import TravelPlanSelector from './fields/TravelPlanSelector.js';
import PushMessagePreview from './fields/PushMessagePreview.js';
import DateTimeInput from './fields/DateTimeInput.js';

import './fields/travelPlanFeedback.css';
import './fields/pushMessageComposer.css';

formToolbarActionRegistry.add('app.download', DownloadToolbarAction);
formToolbarActionRegistry.add('app.release_pdf', ReleasePdfToolbarAction);

fieldRegistry.add('app_travel_plan_feedback', TravelPlanFeedback);
fieldRegistry.add('app_travel_plan_feedback_summary', TravelPlanFeedbackSummary);
fieldRegistry.add('form_mail_text_editor', FormMailTextEditor);
fieldRegistry.add('app_travel_plan_selector', TravelPlanSelector);
fieldRegistry.add('app_push_message_preview', PushMessagePreview);
fieldRegistry.add('app_datetime_input', DateTimeInput);
