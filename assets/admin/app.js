// Add project specific javascript code and import of additional bundles here:
import {formToolbarActionRegistry} from 'sulu-admin-bundle/views';
import {fieldRegistry} from 'sulu-admin-bundle/containers/Form';
import DownloadToolbarAction from './toolbarActions/DownloadToolbarAction.js';
import TravelPlanFeedback from './fields/TravelPlanFeedback.js';
import TravelPlanFeedbackSummary from './fields/TravelPlanFeedbackSummary.js';
import './fields/travelPlanFeedback.css';

formToolbarActionRegistry.add('app.download', DownloadToolbarAction);
fieldRegistry.add('app_travel_plan_feedback', TravelPlanFeedback);
fieldRegistry.add('app_travel_plan_feedback_summary', TravelPlanFeedbackSummary);
