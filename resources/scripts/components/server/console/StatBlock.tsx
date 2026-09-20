import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from './style.module.css';
import CopyOnClick from '@/components/elements/CopyOnClick';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

export default ({ title, copyOnClick, icon, color, className, children }: StatBlockProps) => (
    <CopyOnClick text={copyOnClick}>
        <div className={classNames(styles.stat_block, className)}>
            <div className={classNames(styles.status_bar, color || 'bg-cyan-500')} />
            <div className={classNames(styles.icon, color || 'bg-gray-700')}>
                <Icon icon={icon} className={'text-gray-100'} />
            </div>
            <div className={'flex min-w-0 flex-col justify-center'}>
                <p className={'font-header text-xs font-medium uppercase tracking-wide text-gray-400'}>{title}</p>
                <div className={'mt-1 truncate text-sm sm:text-base font-semibold text-gray-50'}>{children}</div>
            </div>
        </div>
    </CopyOnClick>
);
