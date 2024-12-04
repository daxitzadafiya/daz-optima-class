@extends('layouts.email')

@section('content')
    @inject('app', 'Illuminate\Support\Facades\App')
    
    @php
        $lang = $app->getLocale();
    @endphp

    <table>
        <tr>
            <td>
                <p>
                    Client Name:
                    <?= (isset($model->first_name) ? $model->first_name : 'N/A') . ' ' . (isset($model->last_name) ? $model->last_name : '') ?>
                </p>
                <p>
                    Client Email: <?= $model->email ?>
                </p>

                <?php if ($model->phone != '') : ?>
                    <p>Client Phone: <?= $model->phone ?></p>
                <?php endif ?>

                <?php if ($model->phone != '') : ?>
                    <p>Client Language: <?= isset($lang) ? $lang : '' ?></p>
                <?php endif ?>

                <?php if (isset($other_reference) && !empty($other_reference)) : ?>
                    <p>
                        Property Ref: <?= $other_reference ?>
                    </p>
                <?php else : ?>
                    <?php if ($model->reference != '') : ?>
                        <p>
                            Property Ref: <?= $model->reference ?>
                        </p>
                    <?php endif ?>
                <?php endif ?>

                <?php if ($model->arrival_date != '') : ?>
                    <p>
                        Arrival Date: <?= $model->arrival_date ?>
                    </p>
                <?php endif ?>

                <?php if ($model->departure_date != '') : ?>
                    <p>
                        Departure Date: <?= $model->departure_date ?>
                    </p>
                <?php endif ?>

                <?php if ($model->guests != '') : ?>
                    <p>
                        Number Guests: <?= $model->guests ?>
                    </p>
                <?php endif ?>

                <p>Message From Client: </p>

                <p class="lead">
                    <?= $model->message ?>
                </p>

                <?php if (isset($files) && $files != '') : ?>
                    <p>Attached file: <?= $files ?></p>
                <?php endif ?>
            </td>
        </tr>
    </table>
@endsection