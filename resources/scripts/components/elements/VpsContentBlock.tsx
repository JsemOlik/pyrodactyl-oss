import PageContentBlock, { PageContentBlockProps } from '@/components/elements/PageContentBlock';

import { VpsContext } from '@/state/vps';

interface Props extends PageContentBlockProps {
    title: string;
}

const VpsContentBlock: React.FC<Props> = ({ title, children, ...props }) => {
    const name = VpsContext.useStoreState((state) => state.vps.data?.name);

    return (
        <PageContentBlock title={name ? `${title} - ${name}` : title} {...props}>
            {children}
        </PageContentBlock>
    );
};

export default VpsContentBlock;
