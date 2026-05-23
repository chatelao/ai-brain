import React from 'react';
import Link from 'next/link';
import { useNotifications, useNotificationActions } from '@/hooks/useNotifications';
import { useRelativePath } from '@/hooks/useRelativePath';
import { components } from '@/types/api';

type Notification = components['schemas']['Notification'];

interface NotificationDropdownProps {
  onClose: () => void;
}

const NotificationDropdown: React.FC<NotificationDropdownProps> = ({ onClose }) => {
  const { data, isLoading } = useNotifications('list');
  const { markAsRead, clearAll } = useNotificationActions();
  const { rel } = useRelativePath();

  const notifications = data?.notifications || [];

  return (
    <div className="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
      <div className="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
        <span className="text-sm font-bold text-gray-700">Notifications</span>
      </div>
      <div className="max-h-96 overflow-y-auto">
        {isLoading ? (
          <div className="p-4 text-center text-gray-500 text-sm">Loading...</div>
        ) : notifications.length === 0 ? (
          <div className="p-4 text-center text-gray-500 text-sm">No notifications yet.</div>
        ) : (
          notifications.map((n) => (
            <div
              key={n.notification_id}
              className={`p-3 border-b border-gray-50 hover:bg-gray-50 transition-colors relative cursor-pointer ${
                !n.is_read ? 'bg-blue-50/30' : ''
              }`}
              onClick={() => {
                if (n.notification_id) markAsRead(n.notification_id);
                if (n.data?.source_url) {
                   window.open(n.data.source_url, '_blank');
                }
              }}
            >
              <div className="flex justify-between items-start">
                <div
                  className="text-xs font-bold text-gray-900 prose prose-xs"
                  dangerouslySetInnerHTML={{ __html: n.title || '' }}
                />
                <span className="text-[10px] text-gray-400 shrink-0 ml-2">
                  {n.created_at ? new Date(n.created_at).toLocaleDateString() : ''}
                </span>
              </div>
              {n.github_repo && (
                <div className="text-[10px] text-gray-500 italic mt-0.5">{n.github_repo}</div>
              )}
              <div
                className="text-xs text-gray-600 mt-1 prose prose-xs"
                dangerouslySetInnerHTML={{ __html: n.message || '' }}
              />
              {!n.is_read && (
                <div className="absolute top-3 right-3 w-2 h-2 bg-blue-600 rounded-full"></div>
              )}
            </div>
          ))
        )}
      </div>
      <div className="p-2 border-t border-gray-100 flex justify-center space-x-6 bg-gray-50">
        <button
          onClick={(e) => {
            e.stopPropagation();
            clearAll();
          }}
          className="text-xs text-blue-600 hover:underline"
        >
          Clear all
        </button>
        <Link
          href={rel('/notifications')}
          onClick={onClose}
          className="text-xs text-blue-600 hover:underline"
        >
          View all
        </Link>
      </div>
    </div>
  );
};

export default NotificationDropdown;
