import {
    createColumnHelper,
    type AccessorFn,
    type AccessorFnColumnDef,
    type AccessorKeyColumnDef,
    type DeepKeys,
    type DeepValue,
    type DisplayColumnDef,
    type GroupColumnDef,
    type IdentifiedColumnDef,
    type RowData,
} from '@tanstack/react-table';
import type { DataTableFeatures } from './features';

type Fillable<T> = T & { fill?: boolean };

function withFill<T extends { meta?: object }>(column: Fillable<T>): T {
    const { fill, ...rest } = column;

    if (!fill) {
        return rest as T;
    }

    return {
        ...(rest as T),
        meta: {
            ...((rest as T).meta ?? {}),
            fill: true,
        },
    };
}

export function createDataTableColumnHelper<TData extends RowData>() {
    const helper = createColumnHelper<DataTableFeatures, TData>();

    function accessor<
        TAccessor extends AccessorFn<TData> | DeepKeys<TData>,
        TValue extends TAccessor extends AccessorFn<TData, infer TReturn>
            ? TReturn
            : TAccessor extends DeepKeys<TData>
              ? DeepValue<TData, TAccessor>
              : never,
    >(
        columnAccessor: TAccessor,
        column: Fillable<
            TAccessor extends AccessorFn<TData>
                ? DisplayColumnDef<DataTableFeatures, TData, TValue>
                : IdentifiedColumnDef<DataTableFeatures, TData, TValue>
        >,
    ): TAccessor extends AccessorFn<TData>
        ? AccessorFnColumnDef<DataTableFeatures, TData, TValue>
        : AccessorKeyColumnDef<DataTableFeatures, TData, TValue> {
        return helper.accessor(columnAccessor, withFill(column));
    }

    return {
        accessor,
        display: (column: Fillable<DisplayColumnDef<DataTableFeatures, TData>>) =>
            helper.display(withFill(column)),
        group: (
            column: Fillable<GroupColumnDef<DataTableFeatures, TData, unknown>>,
        ) => helper.group(withFill(column)),
        columns: helper.columns,
    };
}
