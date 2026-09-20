import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    children: React.ReactNode;
}

export default ({ title, legend, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center justify-between border-b border-gray-700 px-4 py-3'}>
            <h3 className={'font-header font-semibold text-gray-100 transition-colors duration-100 group-hover:text-gray-50'}>
                {title}
            </h3>
            {legend && <div className={'text-sm flex items-center'}>{legend}</div>}
        </div>
        <div className={'z-10 px-2 pt-3 pb-2'}>{children}</div>
    </div>
);
