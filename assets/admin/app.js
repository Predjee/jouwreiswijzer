// Add project specific javascript code and import of additional bundles here:
import {formToolbarActionRegistry} from 'sulu-admin-bundle/views';
import DownloadToolbarAction from './toolbarActions/DownloadToolbarAction.js';

formToolbarActionRegistry.add('app.download', DownloadToolbarAction);
